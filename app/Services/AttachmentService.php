<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HasEvidence;
use App\Models\Attachment;
use App\Support\ProcessesImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * ຮັບໄຟລ໌ຫຼັກຖານ (ໃບໂອນ, ໃບບິນ, ຮູບໜ້າງານ) — docs/architecture.md ພາກ 4.3.
 *
 * ຫ້າໜ້າທີ່ທີ່ຂາດບໍ່ໄດ້:
 *   1. ກວດ MIME ຈາກ magic bytes ບໍ່ແມ່ນ extension
 *   2. ຊື່ໄຟລ໌ = UUID ບໍ່ແມ່ນຊື່ເດີມ (ກັນ path traversal)
 *   3. Re-encode ຮູບ → ລ້າງ EXIF (ພິກັດ GPS!) ແລະ payload ທີ່ຝັງໃນ metadata
 *   4. checksum ກັນໄຟລ໌ຊ້ຳ + ພິສູດຄວາມຄົບຖ້ວນຕອນກວດສອບ
 *   5. ເກັບລົງ private disk ເທົ່ານັ້ນ
 */
final class AttachmentService
{
    // ຂອບເຂດ pixel + ການຍົກ memory_limit ໃຊ້ຮ່ວມກັບ BankLogoService
    use ProcessesImages;

    /**
     * ອະນຸຍາດສະເພາະປະເພດທີ່ຮູ້ຈັກ — HEIC ບໍ່ຢູ່ໃນນີ້ເພາະ GD ອ່ານບໍ່ໄດ້.
     * (ໂທລະສັບ iPhone ສ່ວນຫຼາຍຕັ້ງເປັນ "ເຂົ້າກັນໄດ້ຫຼາຍທີ່ສຸດ" = JPEG ຢູ່ແລ້ວ)
     */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    /** ໃບໂອນບໍ່ຕ້ອງການ resolution ສູງກວ່ານີ້ — ຫຍໍ້ລົງປະຢັດພື້ນທີ່ຫຼາຍ */
    private const MAX_WIDTH = 2000;

    private const THUMB_SIZE = 320;

    // ຂອບເຂດ pixel ແລະ ໜ່ວຍຄວາມຈຳ ຕັ້ງໃນ config/evidence.php

    public function __construct(private ImageManager $images) {}

    /**
     * ຕ້ອງເປັນທັງ Eloquent model **ແລະ** ລາຍການທີ່ແນບຫຼັກຖານໄດ້ —
     * ໃຊ້ຄວາມສາມາດຂອງທັງສອງ (getMorphClass/increment ຈາກ Model,
     * attachments()/attachments_count ຈາກ HasEvidence).
     *
     * @param  Model&HasEvidence  $attachable
     */
    public function attach(HasEvidence $attachable, UploadedFile $file, string $kind, int $userId): Attachment
    {
        // ---------- 1. ກວດ MIME ຈາກ content ຈິງ ບໍ່ແມ່ນ extension ----------
        // ຜູ້ໂຈມຕີປ່ຽນຊື່ shell.php → shell.jpg ໄດ້ ແຕ່ປ່ຽນ magic bytes ບໍ່ໄດ້ງ່າຍ
        $realMime = $file->getMimeType();

        if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("ປະເພດໄຟລ໌ບໍ່ອະນຸຍາດ: {$realMime}");
        }

        // ---------- 2. checksum ສຳລັບກວດຊ້ຳ + tamper detection ----------
        $checksum = hash_file('sha256', $file->getRealPath());

        $duplicate = Attachment::query()
            ->where('checksum_sha256', $checksum)
            ->where('attachable_type', $attachable->getMorphClass())
            ->where('attachable_id', $attachable->getKey())
            ->first();

        if ($duplicate !== null) {
            // idempotent — ອັບໄຟລ໌ດຽວກັນຊ້ຳບໍ່ສ້າງ record ໃໝ່ ແລະ ບໍ່ນັບ counter ຊ້ຳ.
            // ເກີດຂຶ້ນຈິງເມື່ອຜູ້ໃຊ້ກົດ "ອັບໂຫຼດ" ສອງເທື່ອຍ້ອນເນັດຊ້າ.
            return $duplicate;
        }

        // ---------- 3. ສ້າງ path ຈາກ UUID ເທົ່ານັ້ນ ----------
        // ຫ້າມໃຊ້ getClientOriginalName() ສ້າງ path — path traversal risk
        $uuid = (string) Str::uuid7();
        $isPdf = $realMime === 'application/pdf';
        $dir = sprintf('evidence/%s/%s', now()->format('Y/m'), $attachable->getTable());
        $path = sprintf('%s/%s.%s', $dir, $uuid, $isPdf ? 'pdf' : 'webp');

        $disk = Storage::disk('private');
        $width = null;
        $height = null;
        $thumbPath = null;

        if ($isPdf) {
            // PDF ບໍ່ຖືກ re-encode — ເກັບຕາມເດີມ. ຄວາມສ່ຽງຄຸມດ້ວຍ nosniff
            // + Content-Disposition ຕອນ download ແທນ (ເບິ່ງ AttachmentController)
            $disk->put($path, (string) file_get_contents($file->getRealPath()));
            $size = (int) $file->getSize();
        } else {
            $this->assertNotTooLarge($file->getRealPath());
            $previousMemoryLimit = $this->raiseMemoryLimit();

            // ---------- 4. Re-encode ຮູບ ----------
            // ລ້າງ EXIF (ພິກັດ GPS ຂອງພະນັກງານ!), ລ້າງ payload ທີ່ຝັງໃນ metadata,
            // ຫຍໍ້ຂະໜາດ (ໄຟລ໌ Excel ເດີມມີຮູບ 2.5 MB — WebP ຫຍໍ້ໄດ້ ~80%)
            //
            // `strip: true` ລະບຸຢ່າງຈະແຈ້ງ ບໍ່ປ່ອຍໃຫ້ເປັນ default ຂອງ library —
            // ການລ້າງ EXIF ຄືເຫດຜົນດ້ານຄວາມປອດໄພ ບໍ່ແມ່ນລາຍລະອຽດຄວາມສວຍງາມ.
            //
            // ⚠️ decode **ເທື່ອດຽວ** ແລ້ວໃຊ້ຄືນ: GD ຄາຍຮູບເປັນ raw pixel
            // (4000×3000 ≈ 48 MB) — decode ສອງເທື່ອເພື່ອເຮັດ thumbnail
            // ຈະໃຊ້ ~96 MB ແລ້ວທະລຸ memory_limit 128 MB ດ້ວຍຮູບມືຖືທຳມະດາ.
            $image = $this->images->decodePath($file->getRealPath())
                ->scaleDown(width: self::MAX_WIDTH);

            $encoded = (string) $image->encode(new WebpEncoder(quality: 82, strip: true));
            $disk->put($path, $encoded);

            $width = $image->width();
            $height = $image->height();
            $size = strlen($encoded);

            // thumbnail ສຳລັບ grid — ໂຫຼດຮູບເຕັມ 65 ຮູບພ້ອມກັນຈະຊ້າຫຼາຍ.
            // ຕັດຈາກຮູບທີ່ຫຍໍ້ແລ້ວ (≤ 2000 px) ບໍ່ແມ່ນຈາກຕົ້ນສະບັບ —
            // ຄຸນນະພາບຢູ່ 320 px ບໍ່ຕ່າງກັນເລີຍ ແຕ່ປະຢັດໜ່ວຍຄວາມຈຳເຄິ່ງໜຶ່ງ.
            $thumbPath = sprintf('%s/%s_thumb.webp', $dir, $uuid);
            $thumb = $image->cover(self::THUMB_SIZE, self::THUMB_SIZE);

            $disk->put($thumbPath, (string) $thumb->encode(new WebpEncoder(quality: 70, strip: true)));

            $this->restoreMemoryLimit($previousMemoryLimit);
        }

        return DB::transaction(function () use (
            $attachable, $uuid, $path, $thumbPath, $file, $realMime,
            $size, $width, $height, $checksum, $kind, $userId
        ): Attachment {
            /** @var Attachment $attachment */
            $attachment = $attachable->attachments()->create([
                'uuid' => $uuid,
                'kind' => $kind,
                'disk' => 'private',
                'path' => $path,
                'thumbnail_path' => $thumbPath,
                // ເກັບຊື່ເດີມໄວ້ສະແດງເທົ່ານັ້ນ — Vue ຕ້ອງ escape ຕອນ render (XSS)
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $realMime,
                'size_bytes' => $size,
                'width' => $width,
                'height' => $height,
                'checksum_sha256' => $checksum,
                'uploaded_by' => $userId,
            ]);

            // counter cache — atomic ກັນ race condition ເມື່ອອັບຫຼາຍໄຟລ໌ພ້ອມກັນ
            $attachable->increment('attachments_count');

            return $attachment;
        });
    }

    /**
     * ລຶບຫຼັກຖານ — soft delete ໃນ DB ແຕ່ **ບໍ່ລຶບໄຟລ໌ຈິງ**.
     *
     * ເຫດຜົນ: ນີ້ຄືຫຼັກຖານການເງິນ. ຖ້າລຶບໄຟລ໌ຖາວອນ ການ restore ແຖວທີ່
     * soft-delete ໄວ້ຈະໄດ້ record ທີ່ຊີ້ໄປຫາໄຟລ໌ທີ່ບໍ່ມີແລ້ວ. ພື້ນທີ່ຖືກ
     * ເກັບກູ້ດ້ວຍ job ແຍກຕ່າງຫາກຫຼັງພົ້ນໄລຍະເກັບຮັກສາ.
     */
    public function detach(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment): void {
            $attachable = $attachment->attachable;

            $attachment->delete();

            // `attachable` ເປັນ morphTo ຈຶ່ງເປັນ Model ລ້າໆ — ກວດ instanceof
            // ເພື່ອໃຫ້ແນ່ໃຈວ່າມີ counter ກ່ອນຫຼຸດ
            if (! $attachable instanceof HasEvidence) {
                return;
            }

            // ຫຼຸດແບບ atomic ດ້ວຍ UPDATE ດຽວທີ່ມີ WHERE guard —
            // ອ່ານຄ່າມາກວດແລ້ວຄ່ອຍຂຽນ (read-then-write) ມີ race: ສອງ request
            // ທີ່ລຶບພ້ອມກັນຈະອ່ານໄດ້ 1 ທັງຄູ່ ແລ້ວຫຼຸດສອງເທື່ອເປັນ -1
            $attachable->newQuery()
                ->whereKey($attachable->getKey())
                ->where('attachments_count', '>', 0)
                ->decrement('attachments_count');
        });
    }
}

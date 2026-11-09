<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bank;
use App\Support\ProcessesImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * ໂລໂກ້ທະນາຄານ — ຄືກັບ AttachmentService ແຕ່ງ່າຍກວ່າ ເພາະໂລໂກ້ເປັນຄ່າ
 * **ອັນດຽວຕໍ່ 1 ທະນາຄານ** ບໍ່ແມ່ນລາຍການໄຟລ໌ ແລະ **ບໍ່ແມ່ນຫຼັກຖານການເງິນ**.
 *
 * ຄວາມຕ່າງທີ່ຕັ້ງໃຈຈາກຫຼັກຖານ:
 *   - ບໍ່ຮັບ PDF (ໂລໂກ້ຄືຮູບ) ແລະ ບໍ່ຮັບ SVG (markup = XSS ຕອນ render inline)
 *   - ບໍ່ມີ checksum/dedup: ໄຟລ໌ຊ້ຳບໍ່ມີຄວາມໝາຍເມື່ອມີໄດ້ອັນດຽວ
 *   - **ລຶບໄຟລ໌ເກົ່າຖິ້ມຈິງ** ຕອນປ່ຽນ/ລຶບ — ບໍ່ມີເຫດຜົນດ້ານການກວດສອບໃຫ້
 *     ເກັບໂລໂກ້ເກົ່າໄວ້ ຄືທີ່ຫຼັກຖານມີ (ເບິ່ງ AttachmentService::detach)
 *
 * ຍັງເກັບຢູ່ disk `private` ຄືເກົ່າ: ລະບົບນີ້ບໍ່ໄດ້ໃຊ້ disk `public` ເລີຍ
 * (docs/architecture.md ພາກ 4.1) — ການເປີດອັນໃໝ່ພຽງເພື່ອໂລໂກ້ຈະເປັນການ
 * ເພີ່ມທາງທີ່ໄຟລ໌ອອກສູ່ພາຍນອກໂດຍບໍ່ຜ່ານ Laravel ໂດຍບໍ່ຈຳເປັນ.
 */
final class BankLogoService
{
    use ProcessesImages;

    /** ບໍ່ມີ PDF ແລະ ບໍ່ມີ SVG — ເບິ່ງເຫດຜົນຢູ່ docblock ຂອງ class */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * ໂລໂກ້ຖືກສະແດງທີ່ 24–48 px ໃນຕາຕະລາງ ແລະ dropdown.
     * 256 px ພຽງພໍສຳລັບຈໍ retina (2×) ແລະ ບໍ່ໃຫຍ່ຈົນເປືອງ.
     */
    private const MAX_SIZE = 256;

    public function __construct(private ImageManager $images) {}

    /**
     * ຮັບໄຟລ໌ໃໝ່ ແລ້ວທັບອັນເກົ່າ (ຖ້າມີ).
     */
    public function store(Bank $bank, UploadedFile $file): Bank
    {
        // ກວດ MIME ຈາກ magic bytes ບໍ່ແມ່ນ extension — ຜູ້ໂຈມຕີປ່ຽນຊື່ໄດ້
        // ແຕ່ປ່ຽນ magic bytes ບໍ່ໄດ້ງ່າຍ. FormRequest ກວດ extension ມາແລ້ວ
        // ອັນນີ້ຄືດ່ານທີສອງທີ່ອີງເນື້ອໄຟລ໌ຈິງ.
        $realMime = $file->getMimeType();

        if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("ປະເພດໄຟລ໌ບໍ່ອະນຸຍາດ: {$realMime}");
        }

        $this->assertNotTooLarge($file->getRealPath());

        $previousMemoryLimit = $this->raiseMemoryLimit();

        try {
            // Re-encode ເປັນ WebP: ລ້າງ EXIF/metadata payload ແລະ ຫຍໍ້ຂະໜາດ.
            // `scaleDown` ຮັກສາອັດຕາສ່ວນ ແລະ ບໍ່ຂະຫຍາຍຮູບນ້ອຍໃຫ້ໃຫຍ່ຂຶ້ນ.
            $image = $this->images->decodePath($file->getRealPath())
                ->scaleDown(width: self::MAX_SIZE, height: self::MAX_SIZE);

            // quality ສູງກວ່າຫຼັກຖານ (82): ໂລໂກ້ມີຂອບຄົມ ແລະ ຕົວອັກສອນນ້ອຍ
            // ທີ່ artifact ຈາກການບີບອັດເຫັນໄດ້ຊັດ ແຕ່ໄຟລ໌ກໍນ້ອຍຢູ່ແລ້ວ
            $encoded = (string) $image->encode(new WebpEncoder(quality: 90, strip: true));
            $width = $image->width();
            $height = $image->height();
        } finally {
            // ຄືນຄ່າແມ່ນແຕ່ຕອນ decode ລົ້ມ — ບໍ່ດັ່ງນັ້ນ request ທີ່ເຫຼືອ
            // ຈະແລ່ນຢູ່ໃນຂອບເຂດທີ່ຖືກຍົກໄວ້ໂດຍບໍ່ຕັ້ງໃຈ
            $this->restoreMemoryLimit($previousMemoryLimit);
        }

        // ຊື່ໄຟລ໌ = UUID ບໍ່ແມ່ນຊື່ເດີມ (path traversal + ຊື່ຮົ່ວຂໍ້ມູນ).
        // UUID ໃໝ່ທຸກເທື່ອທີ່ອັບ ຈຶ່ງບໍ່ຕ້ອງກັງວົນເລື່ອງ cache ຄ້າງຢູ່ browser.
        $path = sprintf('banks/%s.webp', Str::uuid7());
        $disk = Storage::disk('private');

        $disk->put($path, $encoded);

        $old = $bank->logo_path;

        // forceFill: ຄໍລຳໂລໂກ້ບໍ່ຢູ່ໃນ Fillable ໂດຍເຈດຕະນາ — ຈຸດນີ້ຄືບ່ອນດຽວ
        // ທີ່ໄດ້ຮັບອະນຸຍາດໃຫ້ຕັ້ງມັນ
        $bank->forceFill([
            'logo_path' => $path,
            'logo_width' => $width,
            'logo_height' => $height,
            'logo_size_bytes' => strlen($encoded),
            'logo_updated_at' => now(),
        ])->save();

        $this->deleteFile($old);

        return $bank;
    }

    /**
     * ລຶບໂລໂກ້ອອກ — ທະນາຄານຍັງຢູ່.
     */
    public function remove(Bank $bank): Bank
    {
        $old = $bank->logo_path;

        $bank->forceFill([
            'logo_path' => null,
            'logo_width' => null,
            'logo_height' => null,
            'logo_size_bytes' => null,
            'logo_updated_at' => null,
        ])->save();

        $this->deleteFile($old);

        return $bank;
    }

    /**
     * ລຶບໄຟລ໌ **ຫຼັງ** ແຖວຖືກບັນທຶກແລ້ວ.
     *
     * ລຳດັບນີ້ສຳຄັນ: ຖ້າລຶບໄຟລ໌ກ່ອນແລ້ວ save ລົ້ມ ແຖວຈະຍັງຊີ້ໄປຫາໄຟລ໌ທີ່
     * ບໍ່ມີແລ້ວ (ຮູບແຕກຖາວອນ). ເຮັດກັບກັນ ຢ່າງແຮງທີ່ສຸດຄືເຫຼືອໄຟລ໌ກຳພ້າ
     * ໜຶ່ງອັນທີ່ບໍ່ມີໃຜອ້າງເຖິງ.
     */
    private function deleteFile(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('private')->delete($path);
        }
    }
}

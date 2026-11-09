<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\ProcessesImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * ຮູບໂປຣໄຟລ໌ — ຂັ້ນຕອນດຽວກັບ BankLogoService (ເບິ່ງເຫດຜົນລະອຽດຢູ່ນັ້ນ):
 * ກວດ MIME ຈາກເນື້ອໄຟລ໌, ກັນ decompression bomb, re-encode ເປັນ WebP ພ້ອມ
 * `strip` (ລ້າງ EXIF — ຮູບຈາກມືຖືມີພິກັດ GPS ຂອງບ້ານຜູ້ໃຊ້ຕິດມາ), ຊື່ໄຟລ໌
 * ເປັນ UUID, ເກັບຢູ່ disk `private`.
 *
 * ບໍ່ crop ເປັນສີ່ຫຼ່ຽມ: ວົງມົນໃນໜ້າຈໍໃຊ້ `object-cover` ຕັດເອົາເອງ ສ່ວນ
 * lightbox ສະແດງຮູບເຕັມທີ່ຜູ້ໃຊ້ອັບມາ — crop ຢູ່ server ຈະເສຍສ່ວນນັ້ນໄປຖາວອນ.
 */
final class AvatarService
{
    use ProcessesImages;

    /** ບໍ່ມີ SVG (markup = XSS) ແລະ ບໍ່ມີ GIF (GD ເອົາໄດ້ frame ດຽວແບບງຽບໆ) */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** ພໍສຳລັບ lightbox ເຕັມຈໍ ແລະ ວົງມົນ 80 px ຢູ່ຈໍ retina */
    private const MAX_SIZE = 1024;

    public function __construct(private ImageManager $images) {}

    public function store(User $user, UploadedFile $file): User
    {
        $realMime = $file->getMimeType();

        if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("ປະເພດໄຟລ໌ບໍ່ອະນຸຍາດ: {$realMime}");
        }

        $this->assertNotTooLarge($file->getRealPath());

        $previousMemoryLimit = $this->raiseMemoryLimit();

        try {
            $encoded = (string) $this->images->decodePath($file->getRealPath())
                ->scaleDown(width: self::MAX_SIZE, height: self::MAX_SIZE)
                ->encode(new WebpEncoder(quality: 85, strip: true));
        } finally {
            $this->restoreMemoryLimit($previousMemoryLimit);
        }

        $path = sprintf('avatars/%s.webp', Str::uuid7());

        Storage::disk('private')->put($path, $encoded);

        $old = $user->avatar_path;

        // forceFill: ຄໍລຳຮູບບໍ່ຢູ່ໃນ Fillable — ຟອມແກ້ຜູ້ໃຊ້ສົ່ງ path ມາເອງບໍ່ໄດ້
        $user->forceFill([
            'avatar_path' => $path,
            'avatar_updated_at' => now(),
        ])->save();

        // ລຶບອັນເກົ່າ **ຫຼັງ** save — ລົ້ມກາງທາງຢ່າງຮ້າຍຄືເຫຼືອໄຟລ໌ກຳພ້າ ບໍ່ແມ່ນຮູບແຕກ
        $this->deleteFile($old);

        return $user;
    }

    public function remove(User $user): User
    {
        $old = $user->avatar_path;

        if ($old === null) {
            return $user;
        }

        $user->forceFill([
            'avatar_path' => null,
            'avatar_updated_at' => null,
        ])->save();

        $this->deleteFile($old);

        return $user;
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('private')->delete($path);
        }
    }
}

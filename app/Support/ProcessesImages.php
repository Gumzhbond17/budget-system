<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * ສອງເລື່ອງທີ່ທຸກຈຸດທີ່ decode ຮູບຕ້ອງເຮັດ ບໍ່ວ່າຮູບນັ້ນຈະເປັນຫຍັງ —
 * ຫຼັກຖານການຈ່າຍ (AttachmentService) ຫຼື ໂລໂກ້ທະນາຄານ (BankLogoService).
 *
 * ບໍ່ໄດ້ແຍກອອກມາເພື່ອຄວາມສວຍງາມ ແຕ່ເພາະທັງສອງເປັນ **ກົນໄກຄວາມປອດໄພ/ຄວາມ
 * ໝັ້ນຄົງ** ທີ່ຖ້າລືມໃສ່ບ່ອນໃດບ່ອນໜຶ່ງ process ຈະຕາຍໂດຍບໍ່ມີຂໍ້ຄວາມທີ່ອ່ານໄດ້.
 */
trait ProcessesImages
{
    /**
     * ປະຕິເສດຮູບທີ່ຄາຍອອກມາໃຫຍ່ເກີນໄປ **ກ່ອນ** ຈະ decode.
     *
     * getimagesize() ອ່ານແຕ່ header ຈຶ່ງບໍ່ໄດ້ຈອງໜ່ວຍຄວາມຈຳຕາມຂະໜາດຮູບ —
     * ຕ່າງຈາກການ decode ທີ່ຈະຕາຍກ່ອນມີໂອກາດກວດ.
     */
    protected function assertNotTooLarge(string $realPath): void
    {
        $info = @getimagesize($realPath);

        if ($info === false) {
            throw new RuntimeException('ອ່ານຂະໜາດຮູບບໍ່ໄດ້ — ໄຟລ໌ອາດເສຍຫາຍ');
        }

        [$width, $height] = $info;
        $maxPixels = (int) config('evidence.max_pixels', 25_000_000);

        if ($width * $height > $maxPixels) {
            throw new RuntimeException(sprintf(
                'ຮູບໃຫຍ່ເກີນໄປ (%d×%d pixel) — ສູງສຸດ %d ລ້ານ pixel',
                $width,
                $height,
                (int) ($maxPixels / 1_000_000),
            ));
        }
    }

    /**
     * ຍົກ memory_limit ຊົ່ວຄາວສຳລັບການປະມວນຜົນຮູບ.
     *
     * ບໍ່ແມ່ນການ "ແກ້ດ້ວຍການເພີ່ມໜ່ວຍຄວາມຈຳ" ແບບມັກງ່າຍ: GD ຕ້ອງການ
     * width × height × 4 bytes ຕໍ່ສຳເນົາ ໂດຍທຳມະຊາດ ແລະ ຮູບມືຖື 12 MP
     * ຕ້ອງການ ~96 MB ຢ່າງຫຼີກລ່ຽງບໍ່ໄດ້. ຂອບເຂດ 128 MB ມາດຕະຖານຈຶ່ງບໍ່ພໍ
     * ສຳລັບວຽກນີ້ ແຕ່ພໍສຳລັບ request ອື່ນທັງໝົດ — ຈຶ່ງຍົກສະເພາະຈຸດນີ້
     * ແລ້ວຄືນຄ່າເດີມ ແທນທີ່ຈະຍົກທັງແອັບ.
     *
     * @return string|null ຄ່າເດີມສຳລັບຄືນພາຍຫຼັງ (null = ບໍ່ໄດ້ປ່ຽນ)
     */
    protected function raiseMemoryLimit(): ?string
    {
        $target = config('evidence.memory_limit');

        if (! is_string($target) || $target === '') {
            return null;
        }

        // `memory_limit` ເປັນ directive ມາດຕະຖານ ຈຶ່ງມີຄ່າສະເໝີ — ບໍ່ຕ້ອງກວດ false
        $previous = ini_get('memory_limit');

        // ບໍ່ຕ້ອງກວດຜົນຂອງ ini_set: ຖ້າຕັ້ງບໍ່ໄດ້ (php.ini ລັອກໄວ້) ຄ່າກໍຄົງເດີມ
        // ແລະ ການ "ຄືນ" ໄປຫາຄ່າເດີມນັ້ນກໍບໍ່ໄດ້ເຮັດຫຍັງຜິດ
        @ini_set('memory_limit', $target);

        return $previous;
    }

    protected function restoreMemoryLimit(?string $previous): void
    {
        if ($previous !== null) {
            @ini_set('memory_limit', $previous);
        }
    }
}

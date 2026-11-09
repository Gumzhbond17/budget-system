<?php

declare(strict_types=1);

namespace App\Domain\Expense;

use DomainException;

/**
 * Workflow ຂອງລາຍຈ່າຍ: draft → submitted → approved
 *                                       ↘ rejected → submitted
 *
 * ຄວາມພະຍາຍາມຂ້າມຂັ້ນ (ເຊັ່ນ draft → approved) ຕ້ອງລົ້ມເຫຼວແບບດັງໆ
 * ບໍ່ແມ່ນປ່ອຍຜ່ານງຽບໆ — ນີ້ຄືເສັ້ນທາງທີ່ຜູ້ກວດສອບຈະຕິດຕາມ.
 */
final class InvalidStatusTransition extends DomainException
{
    public static function from(string $current, string $target): self
    {
        return new self("ປ່ຽນສະຖານະຈາກ '{$current}' ໄປ '{$target}' ບໍ່ໄດ້");
    }

    public static function locked(): self
    {
        return new self('ລາຍການທີ່ອະນຸມັດແລ້ວແກ້ບໍ່ໄດ້ — ຕ້ອງສ້າງລາຍການປັບປຸງ (adjustment) ໃໝ່');
    }
}

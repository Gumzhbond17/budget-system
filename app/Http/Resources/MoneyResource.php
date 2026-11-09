<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Money\Money;

/**
 * ຮູບແບບມາດຕະຖານຂອງຈຳນວນເງິນໃນ API.
 *
 * ສົ່ງ **integer minor + string decimal** ບໍ່ແມ່ນ float —
 * JSON number ຄື IEEE-754 double ຢູ່ຝັ່ງ JS ດັ່ງນັ້ນ 6,559,489,394.82 ຈະກັບມາເປັນ
 * float rounding error ອັນເກົ່າທັນທີ (docs/architecture.md ພາກ 1.4).
 * Frontend format ຈາກ `minor` + `decimals` ເອົາເອງ.
 */
final class MoneyResource
{
    /**
     * @return array{minor: int, decimal: string, currency: string, decimals: int}
     */
    public static function make(Money $money): array
    {
        return [
            'minor' => $money->minor,
            'decimal' => $money->toDecimal(),
            'currency' => $money->currency,
            'decimals' => $money->decimals,
        ];
    }

    /**
     * ສຳລັບຄໍລຳທີ່ສະກຸນຄົງທີ່ຢູ່ແລ້ວ (amount_usd_minor / amount_lak_minor).
     *
     * @return array{minor: int, decimal: string, currency: string, decimals: int}
     */
    public static function fromMinor(int $minor, string $currency, int $decimals): array
    {
        return self::make(new Money($minor, $currency, $decimals));
    }
}

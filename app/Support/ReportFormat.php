<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Money\Money;

/**
 * ການຈັດຮູບແບບຄ່າຕ່າງໆໃນລາຍງານ PDF.
 *
 * ຢູ່ໃນ class ບໍ່ແມ່ນ closure ທີ່ສົ່ງເຂົ້າ view ເພາະ Blade layout ກັບ section
 * ຂອງລູກຖືກ render ຄົນລະຈັງຫວະ — ຕົວແປທີ່ນິຍາມໃນ layout ຈຶ່ງບໍ່ໄປຮອດ section.
 * ໃຊ້ຜ່ານ `@use('App\Support\ReportFormat', 'F')` ແລ້ວເອີ້ນ `F::money(...)`.
 */
final class ReportFormat
{
    /** ປ້າຍສະຖານະເປັນພາສາລາວ — ຄູ່ກັບ `ledger.statusLabel.*` ຢູ່ໄຟລ໌ພາສາຂອງ SPA */
    private const STATUS_LABELS = [
        'draft' => 'ຮ່າງ',
        'submitted' => 'ສົ່ງແລ້ວ',
        'approved' => 'ອະນຸມັດແລ້ວ',
        'rejected' => 'ປະຕິເສດ',
    ];

    /**
     * ຈຳນວນເງິນທີ່ມາຈາກ MoneyResource (array) — ເຊັ່ນ ຍອດລວມຂອງລາຍງານ.
     *
     * @param  array{minor: int, decimal: string, currency: string, decimals: int}|null  $money
     */
    public static function money(?array $money): string
    {
        if ($money === null) {
            return '—';
        }

        return (new Money($money['minor'], $money['currency'], $money['decimals']))->toFormatted();
    }

    /** ຄໍລຳ amount_usd_minor ເປັນ cents ດິບ (ບໍ່ໄດ້ cast ເປັນ Money ເພາະສະກຸນຄົງທີ່) */
    public static function usd(int $minor): string
    {
        return (new Money($minor, 'USD', 2))->toFormatted();
    }

    /** ຄໍລຳ amount_lak_minor — ກີບບໍ່ມີຫົວໜ່ວຍຍ່ອຍ ຈຶ່ງ decimals = 0 */
    public static function lak(int $minor): string
    {
        return (new Money($minor, 'LAK', 0))->toFormatted();
    }

    /**
     * ຕັດສູນທ້າຍທີ່ບໍ່ມີຄວາມໝາຍ: "21760.0000000000" → "21760".
     *
     * rate ເກັບເປັນ decimal(24,10) ຈຶ່ງກັບມາພ້ອມສູນ 10 ຕົວສະເໝີ — ອ່ານບໍ່ອອກຖ້າບໍ່ຕັດ.
     * ຄູ່ກັບ `trimTrailingZeros()` ຢູ່ resources/js/utils/money.ts.
     */
    public static function rate(string $value): string
    {
        $trimmed = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        return $trimmed === '' ? '0' : $trimmed;
    }

    public static function status(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * ລາຍຊື່ສະຖານະທີ່ລາຍງານນັບ — ໃສ່ໃນຫົວລາຍງານ ເພື່ອບອກວ່າຕົວເລກນີ້ລວມຫຍັງແດ່.
     *
     * @param  list<string>  $statuses
     */
    public static function statusList(array $statuses): string
    {
        return implode(', ', array_map(self::status(...), $statuses));
    }

    /** ຕົວນັບ (ບໍ່ແມ່ນຈຳນວນເງິນ) — ຈັດກຸ່ມຫຼັກພັນຄືກັນເພື່ອໃຫ້ອ່ານງ່າຍ */
    public static function count(int $value): string
    {
        return number_format($value);
    }
}

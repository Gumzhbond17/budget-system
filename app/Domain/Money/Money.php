<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

/**
 * Immutable Value Object ສຳລັບຈຳນວນເງິນ.
 *
 * ເກັບເປັນ integer (minor unit) ສະເໝີ — ບໍ່ມີ float ໃນລະບົບນີ້ເລີຍ.
 *   USD 12.34  → minor 1234, decimals 2
 *   LAK 12,500,000 → minor 12500000, decimals 0
 */
final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
        public int $decimals,
    ) {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException("Invalid currency code: {$currency}");
        }
    }

    /**
     * ສ້າງຈາກຈຳນວນທີ່ຄົນປ້ອນ (ເຊັ່ນ "12,500,000" ຫຼື 12.34)
     *
     * @throws InvalidArgumentException ຖ້າ $amount ບໍ່ແມ່ນຈຳນວນທົດສະນິຍົມທຳມະດາ
     */
    public static function fromDecimal(string|float|int $amount, string $currency, int $decimals): self
    {
        $clean = is_string($amount) ? str_replace([',', ' '], '', $amount) : (string) $amount;
        $clean = DecimalString::assert($clean, 'Money amount');

        // ໃຊ້ bcmath ເພື່ອຫຼີກລ່ຽງ float precision loss
        // bcmul('12.345', '100', 0) = '1234' (truncate) → ຕ້ອງ round ກ່ອນ
        $scaled = bcmul($clean, bcpow('10', (string) $decimals), $decimals + 1);
        $rounded = (int) round((float) $scaled);

        return new self($rounded, strtoupper($currency), $decimals);
    }

    /**
     * ຄືນຄ່າເປັນ string ທົດສະນິຍົມ ສຳລັບສະແດງຜົນ/API
     *
     * @return numeric-string
     */
    public function toDecimal(): string
    {
        if ($this->decimals === 0) {
            return (string) $this->minor;
        }

        return bcdiv((string) $this->minor, bcpow('10', (string) $this->decimals), $this->decimals);
    }

    /**
     * ຈັດກຸ່ມຫຼັກພັນສຳລັບພິມ: 12500000 → "12,500,000".
     *
     * ເຮັດເທິງ string ລ້ວນໆ ບໍ່ຜ່ານ float ຈັກບ່ອນ — `number_format()` ຮັບແຕ່ float
     * ຈຶ່ງເປັນປະຕູຫຼັງທີ່ບັນຫາ 6,559,489,394.82 ຂອງ Excel ຈະກັບເຂົ້າມາໄດ້ (ພາກ 1.4).
     * ຄູ່ກັບ `groupNumeric()` ຢູ່ resources/js/utils/money.ts.
     */
    public function toFormatted(): string
    {
        [$integer, $fraction] = array_pad(explode('.', $this->toDecimal(), 2), 2, null);

        $sign = str_starts_with($integer, '-') ? '-' : '';
        $grouped = strrev(implode(',', str_split(strrev(ltrim($integer, '-')), 3)));

        return $fraction === null ? $sign.$grouped : "{$sign}{$grouped}.{$fraction}";
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency, $this->decimals);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}

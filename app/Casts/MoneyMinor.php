<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\Money\Money;
use App\Models\Currency;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast BIGINT minor → Money object.
 *
 * ໃຊ້:
 *   protected function casts(): array {
 *       return ['amount_minor' => MoneyMinor::class.':currency_code'];
 *   }
 *
 * ⚠️ Trade-off: cast ນີ້ຕ້ອງຮູ້ decimal_places ຂອງສະກຸນ → ດຶງຈາກ cache
 *    (Currency::decimalsFor() cache ໄວ້ຕະຫຼອດ ບໍ່ຕີ DB ທຸກແຖວ)
 *
 * TSet ເປັນ mixed ໂດຍເຈດຕະນາ — cast ນີ້ຮັບຫຍັງກໍໄດ້ ແລ້ວປະຕິເສດທຸກຢ່າງທີ່ບໍ່ແມ່ນ
 * Money ຫຼື int ຕອນ runtime (float ຈາກ request ຄືສິ່ງທີ່ຕ້ອງກັນ).
 *
 * @implements CastsAttributes<Money, mixed>
 */
final class MoneyMinor implements CastsAttributes
{
    public function __construct(private string $currencyField = 'currency_code') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        /** @var string $currency */
        $currency = $attributes[$this->currencyField] ?? 'USD';

        return new Money((int) $value, $currency, Currency::decimalsFor($currency));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof Money) {
            return [$key => $value->minor];
        }

        // ຮັບ int ໂດຍກົງ (ຖືວ່າເປັນ minor ແລ້ວ) — ຫ້າມຮັບ float ເດັດຂາດ
        if (is_int($value)) {
            return [$key => $value];
        }

        throw new InvalidArgumentException(
            "Attribute {$key} must be Money or int minor units, ".get_debug_type($value).' given'
        );
    }
}

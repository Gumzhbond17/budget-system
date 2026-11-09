<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * ຄໍລຳວັນທີລ້ວນ — ເກັບເປັນ "YYYY-MM-DD" ສະເໝີ ບໍ່ມີເວລາຕິດມາ.
 *
 * ⚠️ ເປັນຫຍັງຈຶ່ງບໍ່ໃຊ້ cast `date` ຂອງ Laravel:
 * Eloquent format ຄ່າວັນທີດ້ວຍ `Y-m-d H:i:s` ຕອນຂຽນລົງ DB. MySQL ຕັດເວລາຖິ້ມເອງ
 * ເພາະຄໍລຳເປັນ DATE ແທ້ ແຕ່ **SQLite ເກັບເປັນ TEXT** ຈຶ່ງໄດ້ "2026-08-21 00:00:00".
 * ຜົນຄື:
 *   WHERE effective_date <= '2026-08-21'   → ບໍ່ພົບແຖວຂອງມື້ນັ້ນ (ປຽບທຽບ string)
 *
 * ນັ້ນເຮັດໃຫ້ rate ທີ່ຫາກໍປ້ອນມື້ນີ້ ໃຊ້ກັບລາຍຈ່າຍຂອງມື້ນີ້ບໍ່ໄດ້ ແລະ ລາຍງານ
 * `date_to=<ມື້ສຸດທ້າຍ>` ຕັດແຖວຂອງມື້ນັ້ນຖິ້ມ — ຜິດງຽບໆທັງສອງກໍລະນີ.
 *
 * @implements CastsAttributes<Carbon, mixed>
 */
final class DateOnly implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse(is_string($value) ? $value : (string) $value)->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => Carbon::parse($value)->toDateString()];
    }
}

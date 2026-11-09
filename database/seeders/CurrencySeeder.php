<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * ສະກຸນເງິນທີ່ພົບໃນ Payment_Solar_3_MW.xlsx (ເບິ່ງ docs/architecture.md ພາກ 1.3).
 *
 * `decimal_places` ຄືສິ່ງທີ່ຕັດສິນວ່າ 1 minor unit = ເທົ່າໃດ:
 *   LAK 0 → minor unit = ກີບ (att ເລີກໃຊ້ແລ້ວ)
 *   THB 2 → minor unit = ສະຕາງ
 *   USD 2 → minor unit = cent
 *
 * idempotent — ແລ່ນຊ້ຳໄດ້ໂດຍບໍ່ສ້າງແຖວຊ້ຳ.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->upsert(
            [
                [
                    'code' => 'LAK',
                    'name_lo' => 'ກີບ',
                    'name_en' => 'Lao Kip',
                    'symbol' => '₭',
                    'decimal_places' => 0,
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'THB',
                    'name_lo' => 'ບາດ',
                    'name_en' => 'Thai Baht',
                    'symbol' => '฿',
                    'decimal_places' => 2,
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'USD',
                    'name_lo' => 'ໂດລາ',
                    'name_en' => 'US Dollar',
                    'symbol' => '$',
                    'decimal_places' => 2,
                    'is_active' => true,
                    'sort_order' => 3,
                ],
            ],
            uniqueBy: ['code'],
            update: ['name_lo', 'name_en', 'symbol', 'decimal_places', 'is_active', 'sort_order'],
        );

        // decimal_places ຖືກ cache ໄວ້ — upsert ບໍ່ຍິງ model event ຈຶ່ງຕ້ອງລ້າງເອງ
        Currency::flushDecimalsCache();
    }
}

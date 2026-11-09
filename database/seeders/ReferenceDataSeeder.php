<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\Payee;
use App\Models\Payer;
use Illuminate\Database\Seeder;

/**
 * ຂໍ້ມູນອ້າງອີງທີ່ໜ້າ "ລາຍຮັບ / ລາຍຈ່າຍ" ຕ້ອງມີກ່ອນຈຶ່ງໃຊ້ງານໄດ້.
 *
 * ໂດຍສະເພາະ `exchange_rates`: ExchangeRateService::rateFor() ໂຍນ
 * RuntimeException ເມື່ອຫາ rate ບໍ່ພົບ ດັ່ງນັ້ນຕາຕະລາງເປົ່າ = ບັນທຶກລາຍການ
 * ສະກຸນກີບບໍ່ໄດ້ເລີຍ (500) ນອກຈາກຜູ້ໃຊ້ຈະພິມ rate ເອງທຸກເທື່ອ.
 *
 * ໝວດ ແລະ ຜູ້ຈ່າຍ ມາຈາກການວິເຄາະ Payment_Solar_3_MW.xlsx
 * (docs/architecture.md ພາກ 1.3) — ບໍ່ແມ່ນຊື່ທີ່ແຕ່ງຂຶ້ນ.
 *
 * idempotent — ແລ່ນຊ້ຳໄດ້ ແລະ ຈະບໍ່ຂຽນທັບ rate ທີ່ຜູ້ໃຊ້ແກ້ເອງແລ້ວ.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * ⚠️ rate ເລີ່ມຕົ້ນ — **ບໍ່ແມ່ນອັດຕາທາງການ**.
     *
     * ເປັນພຽງຄ່າກາງຂອງຂອບເຂດທີ່ພົບໃນ Excel (LAK 21,000–22,508 ແລະ
     * THB 31.2–32.78) ເພື່ອບໍ່ໃຫ້ລະບົບລົ້ມຕອນບັນທຶກລາຍການທຳອິດ.
     * ກ່ອນໃຊ້ງານຈິງຕ້ອງໃສ່ rate ຈາກ BCEL/BOL ຕາມວັນທີແທນ.
     *
     * ຢ່າລືມ: rate ທີ່ຜູ້ໃຊ້ພິມໃສ່ໃນແຕ່ລະລາຍການມີ priority ເໜືອຄ່ານີ້ສະເໝີ
     * ແລະ ຄ່ານັ້ນຄືສິ່ງທີ່ຖືກ snapshot ໄວ້ໃນລາຍການ (ພາກ 2.1.ຂ).
     */
    private const SEED_RATE_DATE = '2024-01-01';   // ກ່ອນລາຍການທຳອິດໃນ Excel (2024-01-21)

    public function run(): void
    {
        $this->seedExchangeRates();
        $this->seedCategories();
        $this->seedPayersAndPayees();
        $this->seedBanks();
    }

    private function seedExchangeRates(): void
    {
        // ໜ້າຈໍຕັ້ງອັດຕາເປັນກີບ: LAK = 1 USD = 21,760 ກີບ (rate_lak null — ຢູ່ໃນ
        // rate_per_usd ແລ້ວ), THB = 1 ບາດ = 675.36 ກີບ (= 21760 ÷ 32.22 ປັດ 2 ຕຳແໜ່ງ)
        $rates = [
            'LAK' => ['rate_per_usd' => '21760.0000000000', 'rate_lak' => null],
            'THB' => ['rate_per_usd' => '32.2200000000', 'rate_lak' => '675.3600000000'],
        ];

        foreach ($rates as $code => $values) {
            // firstOrCreate ບໍ່ແມ່ນ updateOrCreate — ຖ້າຜູ້ໃຊ້ແກ້ rate ນີ້ແລ້ວ
            // ການແລ່ນ seeder ຊ້ຳຕ້ອງບໍ່ຂຽນທັບຄ່າຂອງລາວ
            ExchangeRate::query()->firstOrCreate(
                ['currency_code' => $code, 'effective_date' => self::SEED_RATE_DATE],
                [...$values, 'source' => 'manual'],
            );
        }
    }

    /**
     * ໝວດລາຍຈ່າຍ 14 ໝວດ ພ້ອມຍອດທີ່ພົບໃນ Excel ເປັນເຫດຜົນປະກອບ.
     *
     * `color` ຖືກໃຊ້ໂດຍກົງໃນ chart ຂອງລາຍງານ (ພາກ 6) ຈຶ່ງຕ້ອງແຍກກັນອອກ
     * ດ້ວຍຕາເປົ່າ ບໍ່ແມ່ນພຽງແຕ່ຕ່າງ hex.
     */
    private function seedCategories(): void
    {
        $categories = [
            ['EQUIPMENT', 'ອຸປະກອນຕ່າງໆ', 'Equipment & materials', '#6366f1'],   // 92 ລາຍການ / 114,598 USD
            ['LABOR', 'ຄ່າແຮງ', 'Labour', '#0ea5e9'],                            // 53 / 39,421
            ['MISC', 'ຄ່າໃຊ້ຈ່າຍຕ່າງໆ', 'Miscellaneous', '#64748b'],              // 27 / 32,323
            ['DEPOSIT', 'ຄ່າມັດຈຳ', 'Deposit', '#f59e0b'],                        // 1 / 30,000
            ['RENT', 'ຄ່າເຊົ່າ', 'Rent', '#a855f7'],                              // 8 / 6,161
            ['CONTRACT', 'ສັນຍາ', 'Contract', '#ec4899'],                        // 1 / 5,000
            ['FUEL', 'ນ້ຳມັນ', 'Fuel', '#ef4444'],                                // 21 / 1,399
            ['EDL', 'EDL', 'Électricité du Laos', '#14b8a6'],                    // 6 / 996
            ['MEALS', 'ອາຫານ', 'Meals', '#84cc16'],                              // 11 / 707
            ['FEES', 'ຄ່າທຳນຽມ', 'Fees & permits', '#f97316'],
            ['ADMIN', 'ຄ່າບໍລິຫານ', 'Administration', '#8b5cf6'],
            ['REPAIRS', 'ຄ່າສ້ອມແປງ', 'Repairs & maintenance', '#06b6d4'],
            ['ELECTRICITY', 'ຄ່າໄຟຟ້າ', 'Electricity', '#eab308'],
            ['TRANSPORT', 'ຄ່າຂົນສົ່ງ', 'Transport & delivery', '#22c55e'],
        ];

        foreach ($categories as $index => [$code, $nameLo, $nameEn, $color]) {
            ExpenseCategory::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_lo' => $nameLo,
                    'name_en' => $nameEn,
                    'color' => $color,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    /**
     * ຜູ້ຈ່າຍທັງ 3 ມາຈາກ sheet `Sheet1` (ລາຍງານຕາມຜູ້ຈ່າຍ).
     *
     * EDL ເປັນ payee ດຽວທີ່ seed — ທຸກໂຄງການໄຟຟ້າຕ້ອງຈ່າຍໃຫ້ລາວ.
     * ຮ້ານຄ້າ/ບຸກຄົນອື່ນຂຶ້ນກັບແຕ່ລະໂຄງການ ຜູ້ໃຊ້ເພີ່ມເອງຕອນໃຊ້ງານ.
     */
    private function seedPayersAndPayees(): void
    {
        foreach (['Sinexay', 'SSS', 'Sengsamai'] as $name) {
            Payer::query()->firstOrCreate(['name' => $name], ['type' => 'company']);
        }

        Payee::query()->firstOrCreate(
            ['name' => 'EDL — ລັດວິສາຫະກິດໄຟຟ້າລາວ'],
            ['type' => 'government'],
        );
    }

    /**
     * ທະນາຄານທີ່ພົບໃນໃບໂອນເງິນຂອງໂຄງການ ບວກທະນາຄານໃຫຍ່ໃນ ສປປ ລາວ.
     *
     * ໃຊ້ `firstOrCreate` ບໍ່ແມ່ນ `updateOrCreate` (ຕ່າງຈາກໝວດ): ຜູ້ໃຊ້ອາດ
     * ແກ້ຊື່ ຫຼື ອັບໂລໂກ້ໃສ່ແຖວເຫຼົ່ານີ້ແລ້ວ ແລະ ການແລ່ນ seeder ຊ້ຳຕ້ອງ
     * ບໍ່ລ້າງວຽກນັ້ນຖິ້ມ.
     *
     * ບໍ່ໄດ້ໃສ່ໂລໂກ້ໃຫ້: ໂລໂກ້ຄືເຄື່ອງໝາຍການຄ້າຂອງທະນາຄານ — ຜູ້ໃຊ້ອັບເອງ
     * ຈາກແຫຼ່ງທີ່ຕົນມີສິດໃຊ້ ດີກວ່າໃຫ້ repo ພາໄຟລ໌ພວກນັ້ນຕິດມາ.
     */
    private function seedBanks(): void
    {
        $banks = [
            ['BCEL', 'ທະນາຄານການຄ້າຕ່າງປະເທດລາວ ມະຫາຊົນ', 'Banque pour le Commerce Extérieur Lao Public', 'BCEL', 'COEBLALA'],
            ['LDB', 'ທະນາຄານພັດທະນາລາວ', 'Lao Development Bank', 'LDB', 'LDBKLALA'],
            ['APB', 'ທະນາຄານສົ່ງເສີມກະສິກຳ', 'Agricultural Promotion Bank', 'APB', 'APBKLALA'],
            ['JDB', 'ທະນາຄານຮ່ວມພັດທະນາ', 'Joint Development Bank', 'JDB', 'JDBLLALA'],
            ['BIC', 'ທະນາຄານ ບີໄອຊີ ລາວ', 'BIC Bank Lao', 'BIC', null],
            ['LVB', 'ທະນາຄານຮ່ວມທຸລະກິດ ລາວຫວຽດ', 'Lao-Viet Bank', 'LVB', 'LVBKLALA'],
            ['STB', 'ທະນາຄານ ເອສທີ', 'ST Bank', 'STB', null],
            ['MJB', 'ທະນາຄານ ມາຣູຮານ ເຈແປນ ລາວ', 'Maruhan Japan Bank Lao', 'MJB', null],
        ];

        foreach ($banks as $index => [$code, $nameLo, $nameEn, $shortName, $swift]) {
            Bank::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name_lo' => $nameLo,
                    'name_en' => $nameEn,
                    'short_name' => $shortName,
                    'swift_code' => $swift,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}

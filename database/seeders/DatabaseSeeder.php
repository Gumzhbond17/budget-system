<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ⚠️ ບໍ່ໃຊ້ `WithoutModelEvents` (ຄ່າເລີ່ມຕົ້ນຂອງ Laravel) ໂດຍເຈດຕະນາ:
 * Company ອາໄສ hook `creating`/`created` ເພື່ອຕັ້ງ uuid, path, depth ແລະ
 * ວັນສິ້ນສຸດ trial. ປິດ event ແລ້ວ seeder ຈະສ້າງແຖວທີ່ບໍ່ຄົບຖ້ວນ —
 * ຕົວຈິງຄື NOT NULL constraint failed: companies.uuid.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ສະກຸນເງິນຕ້ອງມາກ່ອນ — ຕາຕະລາງອື່ນເກືອບທັງໝົດມີ FK ໄປຫາ currencies.code
        $this->call(CurrencySeeder::class);

        // ອັດຕາແລກປ່ຽນ, ໝວດ, ຜູ້ຈ່າຍ — ຖ້າຂາດ, ໜ້າ "ລາຍຮັບ/ລາຍຈ່າຍ" ໃຊ້ບໍ່ໄດ້
        // (exchange_rates ເປົ່າ = ບັນທຶກລາຍການສະກຸນກີບແລ້ວໄດ້ 500)
        $this->call(ReferenceDataSeeder::class);

        // ອົງກອນຕ້ອງມາກ່ອນຜູ້ໃຊ້ ແລະ ໂຄງການ (ທັງສອງມີ FK ໄປຫາ companies)
        $company = Company::query()->firstOrCreate(
            ['code' => 'DEFAULT'],
            [
                'name' => 'ອົງກອນເລີ່ມຕົ້ນ',
                'name_en' => 'Default Organisation',
                'type' => 'internal',
                'plan' => 'active',   // ຂໍ້ມູນ dev ຫ້າມຖືກລັອກດ້ວຍ trial ທີ່ໝົດອາຍຸ
            ],
        );

        // ຕ້ອງມີຜູ້ດູແລລະບົບຢ່າງໜ້ອຍ 1 ຄົນ ບໍ່ດັ່ງນັ້ນຈະບໍ່ມີໃຜສ້າງອົງກອນ
        // ຫຼື ຜູ້ໃຊ້ຄົນທຳອິດໄດ້ເລີຍ (ເບິ່ງ CompanyPolicy / UserPolicy)
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'company_id' => $company->id,
                'name' => 'Test User',
                'password' => 'password',
                'is_platform_admin' => true,
                'is_active' => true,
            ],
        );
    }
}

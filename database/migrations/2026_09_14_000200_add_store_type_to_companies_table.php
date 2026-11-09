<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ເພີ່ມປະເພດ `store` (ຮ້ານ) ໃສ່ຕົ້ນໄມ້ອົງກອນ.
 *
 * ເປົ້າໝາຍ: ຮ້ານຂາຍອອນລາຍທີ່ມາໃຊ້ລະບົບ ຄວນ "ໃສ່ຊື່ຮ້ານ ແລ້ວບັນທຶກລາຍຮັບ-ລາຍຈ່າຍ"
 * ໄດ້ເລີຍ ບໍ່ຕ້ອງຜ່ານຂັ້ນຕອນ ອົງກອນ → ໂຄງການ → ມອບສິດ ທີ່ອອກແບບມາສຳລັບ
 * ໂຄງການກໍ່ສ້າງຂະໜາດໃຫຍ່.
 */
return new class extends Migration
{
    public function up(): void
    {
        // enum → string ຕາມແບບດຽວກັນກັບ `project_members.role`
        // (ເບິ່ງ 2026_08_26_000300_rename_project_member_roles).
        //
        // ເຫດຜົນ: ການເພີ່ມຄ່າໃສ່ enum ຕ້ອງ ALTER TABLE ເຊິ່ງລັອກຕາຕະລາງໃນ MySQL
        // ແລະ ໄວຍະກອນການປ່ຽນ enum ຂອງ MySQL ກັບ SQLite ຕ່າງກັນ ຈຶ່ງຂຽນ migration
        // ທີ່ແລ່ນໄດ້ທັງສອງບໍ່ໄດ້. ຄ່າທີ່ອະນຸຍາດຖືກບັງຄັບຢູ່ `Company::TYPES`
        // ພ້ອມກັບ `Rule::in()` ໃນ CompanyController ແທນ.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('type', 20)->default('internal')->change();
        });
    }

    public function down(): void
    {
        // ແຖວປະເພດ `store` ຕ້ອງກັບໄປເປັນຄ່າທີ່ enum ເດີມຮັບໄດ້ ບໍ່ດັ່ງນັ້ນ
        // ການ ALTER ຄືນຈະລົ້ມ. `branch` ໃກ້ຄຽງທີ່ສຸດ — ທັງສອງແມ່ນໜ່ວຍງານລູກ.
        DB::table('companies')
            ->where('type', 'store')
            ->update(['type' => 'branch']);

        Schema::table('companies', function (Blueprint $table) {
            $table->enum('type', ['internal', 'branch', 'third_party'])
                ->default('internal')
                ->change();
        });
    }
};

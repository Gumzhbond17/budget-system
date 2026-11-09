<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ວັນໝົດອາຍຸ **ຕໍ່ບັນຊີ** — ເພີ່ມເຕີມຈາກອາຍຸຂອງອົງກອນ ບໍ່ແມ່ນແທນມັນ.
            //
            // ຂໍ້ຕົກລົງ (ສຳຄັນ): ວັນນີ້ **ຫຍໍ້ໃຫ້ສັ້ນລົງ** ໄດ້ຢ່າງດຽວ —
            // ຜູ້ໃຊ້ເຂົ້າລະບົບໄດ້ກໍຕໍ່ເມື່ອ *ທັງສອງ* ຍັງບໍ່ໝົດ (ເບິ່ງ User::canAccessSystem).
            // ຕັ້ງວັນຊ້າກວ່າອົງກອນຈຶ່ງບໍ່ເຮັດໃຫ້ໃຊ້ໄດ້ດົນກວ່າອົງກອນ.
            //
            // ເຫດຜົນ: ໃຊ້ກັບພະນັກງານສັນຍາຈ້າງ/ຜູ້ກວດສອບພາຍນອກ ທີ່ຕ້ອງໝົດສິດ
            // ກ່ອນໝູ່ຄົນອື່ນ ໂດຍບໍ່ໄປແຕະອາຍຸຂອງອົງກອນທັງໝົດ.
            //
            // null = ບໍ່ຈຳກັດລາຍຄົນ (ໃຊ້ຕາມອົງກອນຢ່າງດຽວ) ← ຄ່າເລີ່ມຕົ້ນຂອງແຖວເກົ່າ
            $table->date('access_expires_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('access_expires_at');
        });
    }
};

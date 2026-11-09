<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ທະນາຄານທີ່ໃຊ້ໂອນ — ຕອບຄຳຖາມ "ໃບໂອນນີ້ໂອນຜ່ານທະນາຄານໃດ" ຕອນກວດຫຼັກຖານ.
 *
 * `nullOnDelete` ຄືກັບ `payer_id`/`payee_id`/`category_id`: ລຶບທະນາຄານແລ້ວ
 * ລາຍການເກົ່າຍັງຢູ່ຄົບ ພຽງແຕ່ບໍ່ຮູ້ວ່າທະນາຄານໃດ. ເຖິງຢ່າງນັ້ນ
 * `BankController::destroy` ຍັງກັນການລຶບເມື່ອມີລາຍການອ້າງເຖິງຢູ່ (409) —
 * FK ເປັນຕາໜ່າງກັນສຸດທ້າຍ ບໍ່ແມ່ນທາງທຳມະດາ.
 *
 * ບໍ່ໄດ້ຍ້າຍ `payees.bank_name` (string) ມາເປັນ FK ຢູ່ນີ້ — ນັ້ນເປັນຄົນລະ
 * ເລື່ອງ ແລະ ຕ້ອງມີການແປງຂໍ້ມູນຕ່າງຫາກ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bank_id')
                ->nullable()
                ->after('payee_id')
                ->constrained('banks')
                ->nullOnDelete();
        });

        Schema::table('revenues', function (Blueprint $table) {
            // ລາຍຮັບ: "ເງິນເຂົ້າບັນຊີທະນາຄານໃດ" — ຄຳຖາມດຽວກັນ ຄົນລະທິດທາງ
            $table->foreignId('bank_id')
                ->nullable()
                ->after('payer_id')
                ->constrained('banks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('revenues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
        });
    }
};

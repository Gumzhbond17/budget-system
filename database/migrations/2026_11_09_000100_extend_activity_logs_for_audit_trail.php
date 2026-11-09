<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ຂະຫຍາຍ `activity_logs` ໃຫ້ຮັບ audit trail ທົ່ວລະບົບ (ໜ້າ "ບັນທຶກການໃຊ້ງານ").
 *
 * 1. subject ເປັນ nullable — ເຫດການ "ຄົ້ນຫາ" ແລະ "login ລົ້ມເຫຼວດ້ວຍອີເມວທີ່ບໍ່ມີ"
 *    ບໍ່ມີ record ໃດເປັນເປົ້າໝາຍ.
 * 2. `subject_label` — snapshot ຊື່ທີ່ຄົນອ່ານອອກ (EXP-2026-0074, ຊື່ຜູ້ຮັບເງິນ…)
 *    ເກັບຕອນເກີດເຫດການ. ຖ້າໄປ join ເອົາຕອນສະແດງ ແຖວທີ່ຖືກລຶບ ຫຼື ປ່ຽນຊື່ພາຍຫຼັງ
 *    ຈະສະແດງຜິດ ຫຼື ບໍ່ສະແດງເລີຍ — ຂັດກັບຈຸດປະສົງຂອງ audit.
 * 3. index ສຳລັບການກັ່ນຕອງຂອງໜ້າລາຍງານ (ຊ່ວງວັນທີ / ຜູ້ໃຊ້ / ປະເພດເຫດການ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->change();
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->string('subject_label', 255)->nullable()->after('subject_id');

            $table->index('created_at');
            $table->index(['causer_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        // ບໍ່ຄືນ subject ເປັນ NOT NULL: ແຖວ "ຄົ້ນຫາ" / "login ລົ້ມເຫຼວ" ມີ subject
        // ຫວ່າງ ແລະ ຫ້າມລຶບ log (docs/architecture.md ພາກ 9) — rollback ຈຶ່ງເອົາ
        // ອອກແຕ່ສິ່ງທີ່ເພີ່ມມາ
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['event', 'created_at']);
            $table->dropIndex(['causer_id', 'created_at']);
            $table->dropIndex(['created_at']);
            $table->dropColumn('subject_label');
        });
    }
};

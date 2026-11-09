<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ຍົກ `funding_receipts` ຂຶ້ນເປັນ `revenues` — ລາຍຮັບເປັນພົນລະເມືອງຊັ້ນໜຶ່ງ
 * ຄຽງຄູ່ກັບລາຍຈ່າຍ ບໍ່ແມ່ນພຽງ "ເງິນທຶນທີ່ຮັບ" ອີກຕໍ່ໄປ.
 *
 * ຕາຕະລາງເດີມມີຄໍລຳເງິນຄົບຢູ່ແລ້ວ (FX snapshot + ຍອດແປງ USD/LAK) ຈຶ່ງ
 * ບໍ່ຕ້ອງສ້າງໃໝ່ — ເພີ່ມແຕ່ສິ່ງທີ່ບັນຊີແຍກປະເພດ (GL) ຕ້ອງການ:
 *   doc_no  ເລກທີ່ອ້າງອີງ (REV-2026-0001)
 *   title   ຄຳອະທິບາຍທີ່ສະແດງເປັນແຖວໃນ GL
 *   status  ຂັ້ນຕອນອະນຸມັດ — ຕ້ອງມີ ບໍ່ດັ່ງນັ້ນ GL ຈະເອົາລາຍຈ່າຍທີ່ອະນຸມັດແລ້ວ
 *           ມາປົນກັບລາຍຮັບທຸກສະຖານະ (ລາຍງານ default `statuses = ['approved']`)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('funding_receipts', 'revenues');

        Schema::table('revenues', function (Blueprint $table) {
            $table->string('doc_no', 40)->nullable()->after('uuid');

            // ແຖວ GL ຕ້ອງມີຄຳອະທິບາຍ — `reference`/`note` ເປັນ optional ຈຶ່ງໃຊ້ແທນບໍ່ໄດ້.
            // ຄ່າເລີ່ມຕົ້ນຫວ່າງເພື່ອໃຫ້ເພີ່ມຄໍລຳ NOT NULL ໄດ້; ຕາຕະລາງຍັງເປົ່າຢູ່.
            $table->string('title', 255)->default('')->after('received_date');

            // rate LAK ທີ່ໃຊ້ແປງ — expenses ມີຢູ່ແລ້ວ, revenues ຂາດ.
            // ເກັບໄວ້ເພື່ອຄວາມໂປ່ງໃສຕອນກວດສອບ (ພາກ 2.1.ຂ)
            $table->decimal('fx_rate_lak_per_usd', 24, 10)->default(0)->after('fx_rate_per_usd');

            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                ->default('draft')
                ->after('note');
            $table->foreignId('approved_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');

            // GL ດຶງຕາມ ໂຄງການ + ວັນທີ + ສະຖານະ ສະເໝີ
            $table->index(['project_id', 'received_date'], 'idx_revenue_project_date');
            $table->index(['project_id', 'status'], 'idx_revenue_project_status');
            $table->index('doc_no', 'idx_revenue_doc_no');
        });

        // ແຫຼ່ງລາຍຮັບອາດຍັງບໍ່ຮູ້ຕອນບັນທຶກ — ເດີມບັງຄັບຕ້ອງມີເພາະອອກແບບໄວ້
        // ສຳລັບ "ເງິນທຶນຈາກຜູ້ຈ່າຍ" ເທົ່ານັ້ນ
        Schema::table('revenues', function (Blueprint $table) {
            $table->foreignId('payer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('revenues', function (Blueprint $table) {
            $table->dropIndex('idx_revenue_project_date');
            $table->dropIndex('idx_revenue_project_status');
            $table->dropIndex('idx_revenue_doc_no');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'doc_no', 'title', 'fx_rate_lak_per_usd',
                'status', 'approved_at', 'rejection_reason',
            ]);
        });

        Schema::rename('revenues', 'funding_receipts');
    }
};

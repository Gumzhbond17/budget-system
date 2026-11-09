<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                 // ໃຊ້ໃນ public API / URL

            // ---------- ຄວາມສຳພັນ ----------
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->nullOnDelete();
            $table->foreignId('payee_id')->nullable()->constrained('payees')->nullOnDelete();

            // ---------- ຂໍ້ມູນເອກະສານ ----------
            $table->string('doc_no', 40)->nullable();       // ເລກທີ່ໃບສຳຄັນຈ່າຍ (ອອກອັດຕະໂນມັດ EXP-2026-0001)
            $table->date('expense_date');                   // ວັນທີຈ່າຍຈິງ — DATE ບໍ່ແມ່ນ string
            $table->string('title', 255);                   // ລາຍລະອຽດຫຍໍ້
            $table->text('description')->nullable();

            $table->string('unit', 20)->default('Lot');     // Lot, pcs, Set, Km
            $table->decimal('quantity', 14, 3)->default(1); // ຮອງຮັບ 0.5 ຄິວ, 2.75 ໂຕນ

            // ---------- ຈຳນວນເງິນ (ຫົວໃຈຂອງລະບົບ) ----------
            $table->char('currency_code', 3);               // ສະກຸນທີ່ຈ່າຍຈິງ
            $table->bigInteger('amount_minor');             // ຈຳນວນເງິນໃນ minor unit ຂອງສະກຸນນັ້ນ

            // Snapshot ອັດຕາແລກປ່ຽນ ຕອນບັນທຶກ — ບໍ່ປ່ຽນຕະຫຼອດໄປ (audit requirement)
            // ຄວາມໝາຍ: ຈຳນວນ currency_code ຕໍ່ 1 USD
            $table->decimal('fx_rate_per_usd', 24, 10);

            // ຍອດແປງທີ່ຄິດໄລ່ໄວ້ລ່ວງໜ້າ (denormalized ໂດຍເຈດຕະນາ — ເບິ່ງພາກ 2.1.ຄ)
            $table->bigInteger('amount_usd_minor');         // ສະເໝີເປັນ cents
            $table->bigInteger('amount_lak_minor');         // ສະເໝີເປັນກີບ
            $table->decimal('fx_rate_lak_per_usd', 24, 10); // rate LAK ທີ່ໃຊ້ແປງ (ເກັບໄວ້ເພື່ອຄວາມໂປ່ງໃສ)

            // ---------- ການຈ່າຍ ----------
            $table->enum('payment_method', [
                'cash', 'bank_transfer', 'mobile_banking', 'cheque', 'credit', 'other',
            ])->default('bank_transfer');
            $table->string('payment_reference', 100)->nullable();  // ເລກອ້າງອີງໃບໂອນ
            $table->date('paid_at')->nullable();

            // ---------- Workflow ----------
            // draft → submitted → approved → (rejected)
            // ບັນທຶກທີ່ approved ແລ້ວຈະ lock ບໍ່ໃຫ້ແກ້ (ເບິ່ງ ExpensePolicy)
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // ---------- Metadata ----------
            $table->unsignedTinyInteger('attachments_count')->default(0); // counter cache — ກັນ N+1 ໃນ list
            $table->boolean('has_evidence')->storedAs('attachments_count > 0'); // generated column ສຳລັບ filter "ບໍ່ມີຫຼັກຖານ"
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies');

            // ---------- Indexes ----------
            // ອອກແບບຕາມ query ຈິງທີ່ລາຍງານຈະໃຊ້ ບໍ່ແມ່ນໃສ່ໄປທົ່ວ
            $table->index(['project_id', 'expense_date'], 'idx_project_date');       // ລາຍງານຕາມຊ່ວງເວລາ
            $table->index(['project_id', 'category_id', 'expense_date'], 'idx_project_cat_date'); // chart ຕາມໝວດ
            $table->index(['project_id', 'payer_id'], 'idx_project_payer');          // ລາຍງານຕາມຜູ້ຈ່າຍ
            $table->index(['project_id', 'status'], 'idx_project_status');           // ລໍຖ້າອະນຸມັດ
            $table->index('doc_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

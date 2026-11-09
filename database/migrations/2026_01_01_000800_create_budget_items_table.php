<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('expense_categories')->nullOnDelete();

            $table->string('name', 255);                // ແຜງໂຊລາ ຂະໜາດ 500 W
            $table->string('unit', 20)->default('Lot'); // pcs, Set, Km, Lot
            $table->decimal('quantity', 14, 3)->default(1);

            $table->char('currency_code', 3)->default('USD');
            $table->bigInteger('unit_price_minor');
            // amount = quantity × unit_price — ຄິດໃນ BudgetItemService ກັນຄວາມຜິດພາດຄົນ
            $table->bigInteger('amount_minor');

            // ຍອດ USD ຄິດໄວ້ລ່ວງໜ້າ ດ້ວຍເຫດຜົນດຽວກັນກັບ expenses (ພາກ 2.1.ຄ):
            // ບໍ່ມີຄໍລຳນີ້ ການປຽບທຽບ "ງົບ vs ຈິງ" ຈະທຽບຂ້າມສະກຸນບໍ່ໄດ້ເລີຍ
            $table->decimal('fx_rate_per_usd', 24, 10);   // ຈຳນວນ currency_code ຕໍ່ 1 USD
            $table->bigInteger('amount_usd_minor');       // cents

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['project_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
    }
};

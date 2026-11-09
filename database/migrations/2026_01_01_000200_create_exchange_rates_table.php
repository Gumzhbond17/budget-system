<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            $table->char('currency_code', 3);   // ສະກຸນທີ່ຖືກອ້າງອີງ
            $table->date('effective_date');     // rate ຂອງວັນໃດ

            // ຂໍ້ຕົກລົງເຄັ່ງຄັດ: rate = ຈຳນວນ currency_code ຕໍ່ 1 USD
            //   LAK 21760.0000000000  → 1 USD = 21,760 LAK
            //   THB    32.2200000000  → 1 USD = 32.22 THB
            //   USD     1.0000000000
            // ເຫດຜົນ: ຄໍລຳ "ອັດຕາແລກປ່ຽນ" ໃນ Excel ເດີມປົນ 2 ຄວາມໝາຍ ຈົນແຍກບໍ່ອອກ
            $table->decimal('rate_per_usd', 24, 10);

            $table->enum('source', ['manual', 'bcel', 'bol', 'api'])->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // ໜຶ່ງສະກຸນ ໜຶ່ງວັນ ໜຶ່ງ rate — ປ້ອງກັນ rate ຊ້ຳທີ່ເຮັດໃຫ້ລາຍງານບໍ່ແນ່ນອນ
            $table->unique(['currency_code', 'effective_date']);
            $table->index(['currency_code', 'effective_date'], 'idx_rate_lookup');

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};

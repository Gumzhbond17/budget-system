<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ຕິດຕາມ "ລາຍຈ່າຍ / ເງິນທີ່ຮັບ / ຍອດທີ່ຍັງຄ້າງ" ຕໍ່ຜູ້ຈ່າຍ
        Schema::create('funding_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('payers');

            $table->date('received_date');
            $table->char('currency_code', 3);
            $table->bigInteger('amount_minor');
            $table->decimal('fx_rate_per_usd', 24, 10);
            $table->bigInteger('amount_usd_minor');
            $table->bigInteger('amount_lak_minor');

            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('attachments_count')->default(0);

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['project_id', 'payer_id', 'received_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_receipts');
    }
};

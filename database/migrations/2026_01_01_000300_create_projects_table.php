<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();              // ໃຊ້ໃນ URL ແທນ auto-increment id (ກັນ enumeration)
            $table->string('code', 30)->unique();        // SOLAR-3MW, SOLAR-XSB
            $table->string('name');                      // ໂຄງການ Solar 3 MW
            $table->text('description')->nullable();
            $table->string('location')->nullable();      // ນາຊາຍທອງ

            $table->unsignedInteger('capacity_kw')->nullable();   // 3000 kW
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // ງົບປະມານລວມ (ຈາກ sheet ຕົ້ນທຶນ) — ເກັບເປັນ minor units
            $table->char('budget_currency_code', 3)->default('USD');
            $table->bigInteger('budget_amount_minor')->default(0);

            $table->enum('status', ['planning', 'active', 'on_hold', 'completed', 'cancelled'])
                ->default('planning');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();   // ໂຄງການບໍ່ຄວນຖືກລຶບຖາວອນ (ມີຂໍ້ມູນການເງິນຜູກຢູ່)

            $table->foreign('budget_currency_code')->references('code')->on('currencies');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

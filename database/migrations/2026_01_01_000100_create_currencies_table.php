<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            // ໃຊ້ ISO-4217 code ເປັນ primary key — ອ່ານງ່າຍໃນ FK, ບໍ່ຕ້ອງ JOIN ເພື່ອຮູ້ວ່າສະກຸນຫຍັງ
            $table->char('code', 3)->primary();          // LAK, THB, USD
            $table->string('name_lo', 60);               // ກີບ, ບາດ, ໂດລາ
            $table->string('name_en', 60);
            $table->string('symbol', 8);                 // ₭, ฿, $

            // ຈຳນວນທົດສະນິຍົມ — ຕົວກຳນົດວ່າ minor unit ຄູນດ້ວຍເທົ່າໃດ
            // LAK = 0 (ບໍ່ມີຫົວໜ່ວຍຍ່ອຍໃຊ້ຈິງ), THB/USD = 2
            $table->unsignedTinyInteger('decimal_places')->default(2);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed ຂໍ້ມູນເລີ່ມຕົ້ນ (ເບິ່ງ CurrencySeeder)
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};

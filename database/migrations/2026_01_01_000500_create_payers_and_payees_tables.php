<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ຜູ້ຈ່າຍ = ບໍລິສັດ/ຄົນທີ່ອອກເງິນ (Sinexay, SSS, Sengsamai, phanh)
        // ແຍກເປັນຕາຕະລາງ ບໍ່ແມ່ນ string ເພື່ອກັນບັນຫາ "SSS" vs "SSS " vs "Sengsamai/Saengsamai"
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->enum('type', ['company', 'individual'])->default('company');
            $table->string('contact_phone', 30)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ຜູ້ຮັບເງິນ = ຮ້ານຄ້າ/ບຸກຄົນ (ນາງ ບຸດສະບາ, ຮ້ານຫລຽນໄຊ, EDL)
        Schema::create('payees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->enum('type', ['company', 'individual', 'government'])->default('individual');
            $table->string('phone', 30)->nullable();
            $table->string('bank_name', 80)->nullable();
            // ພາກ 9 ບັງຄັບ `encrypted` cast — ciphertext ຂອງ Laravel ຍາວ ~250 ຕົວ
            // ຈຶ່ງໃຊ້ text ບໍ່ແມ່ນ string(60) ຕາມພາກ 2.3 (60 ຕົວຈະ truncate ຈົນຖອດລະຫັດບໍ່ໄດ້)
            $table->text('bank_account_no')->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payees');
        Schema::dropIfExists('payers');
    }
};

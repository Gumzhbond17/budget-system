<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ບັນຊີເຂົ້າໃຊ້ລະບົບຕ່າງໆ (credential vault) — ສະເພາະຜູ້ດູແລລະບົບ.
 *
 * ⚠️ ຕາຕະລາງນີ້ບໍ່ຄືຂໍ້ມູນຫຼັກອື່ນ ຢູ່ 3 ຈຸດ:
 *
 * 1. `password` ເກັບເປັນ **ciphertext** ບໍ່ແມ່ນ hash — ຕ້ອງອ່ານກັບຄືນໄດ້
 *    ຈຶ່ງ hash ບໍ່ໄດ້. ເຂົ້າລະຫັດດ້ວຍ APP_KEY ຜ່ານ cast `encrypted`
 *    (ເບິ່ງ App\Models\Credential). ຖ້າ APP_KEY ຫາຍ ຫຼື ຖືກປ່ຽນ
 *    ລະຫັດທັງໝົດໃນນີ້ຈະກູ້ຄືນບໍ່ໄດ້ຕະຫຼອດໄປ — ສຳຮອງ APP_KEY ໄວ້ນຳ.
 *
 * 2. **ບໍ່ມີ softDeletes** ຕ່າງຈາກລາຍຈ່າຍ/ຫຼັກຖານ: ລາຍການເຫຼົ່ານັ້ນເປັນ
 *    ຫຼັກຖານການເງິນທີ່ຕ້ອງເກັບໄວ້ ແຕ່ອັນນີ້ເປັນຄວາມລັບ — "ລຶບແລ້ວ" ຕ້ອງ
 *    ແປວ່າ ciphertext ບໍ່ຢູ່ໃນ DB ອີກຕໍ່ໄປແທ້ໆ.
 *
 * 3. ອ້າງອີງດ້ວຍ `uuid` ໃນ URL ບໍ່ແມ່ນ id ແບບ payers/banks — ກັນການໄລ່ເບິ່ງ
 *    ວ່າລະບົບມີບັນຊີຢູ່ຈັກອັນ (docs/architecture.md ພາກ 9).
 *
 * ປະຫວັດການເປີດເບິ່ງລະຫັດຖືກບັນທຶກໃສ່ `activity_logs` ທີ່ມີຢູ່ແລ້ວ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title', 160);

            // ບໍ່ຈຳກັດເປັນ URL: ລາຍການຈິງມີທັງ "https://..." ແລະ "MOBILE APP",
            // "App" — ບ່ອນທີ່ໃຊ້ບັນຊີນີ້ ບໍ່ແມ່ນທຸກອັນຈະເປັນເວັບ
            $table->string('url_link', 500)->nullable();
            $table->string('username', 190)->nullable();

            // TEXT ບໍ່ແມ່ນ VARCHAR: ciphertext ຂອງ Laravel ຍາວກວ່າຄ່າຈິງ
            // ຫຼາຍເທົ່າ (IV + MAC + base64) — ລະຫັດ 20 ຕົວອອກມາ ~250 ຕົວ
            $table->text('password');

            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // ລຳດັບທີ່ໜ້າຈັດການດຶງສະເໝີ
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};

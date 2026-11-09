<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ທະນາຄານ — ຂໍ້ມູນຫຼັກທີ່ໃຊ້ຮ່ວມກັນທຸກໂຄງການ ຄືກັບ payers/payees/categories.
 *
 * ⚠️ ຍັງບໍ່ມີຕາຕະລາງໃດອ້າງເຖິງ: `payees.bank_name` ຍັງເປັນ string ອິດສະຫຼະຢູ່.
 * ການປ່ຽນມັນເປັນ FK `bank_id` ຕ້ອງມີການແປງຂໍ້ມູນ (ຊື່ທີ່ພິມໄວ້ແລ້ວບໍ່ຈຳເປັນ
 * ຕ້ອງກົງກັບແຖວໃນນີ້) ຈຶ່ງແຍກເປັນວຽກຕ່າງຫາກ ບໍ່ໄດ້ເຮັດພ້ອມກັນຢູ່ນີ້.
 *
 * ໂລໂກ້ເກັບເປັນ path ຢູ່ disk `private` (ຄືກັບຫຼັກຖານ) ແລະ ເສີບຜ່ານ signed
 * route — ບໍ່ມີ disk `public` ໃນລະບົບນີ້ (docs/architecture.md ພາກ 4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();

            // ລະຫັດຫຍໍ້ທີ່ຄົນລາວໃຊ້ເອີ້ນຈິງ: BCEL, LDB, APB, JDB, BIC
            $table->string('code', 20)->unique();
            $table->string('name_lo', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('short_name', 40)->nullable();

            // SWIFT/BIC — 8 ຫຼື 11 ຕົວ (ISO-9362). ບໍ່ unique: ສາຂາຕ່າງກັນ
            // ຂອງທະນາຄານດຽວກັນໃຊ້ 8 ຕົວທຳອິດຮ່ວມກັນໄດ້
            $table->string('swift_code', 11)->nullable();

            // ---------- ໂລໂກ້ ----------
            // ໄຟລ໌ຖືກ re-encode ເປັນ WebP ສະເໝີ ຈຶ່ງບໍ່ຕ້ອງເກັບ mime_type
            $table->string('logo_path', 500)->nullable();
            $table->unsignedInteger('logo_width')->nullable();
            $table->unsignedInteger('logo_height')->nullable();
            $table->unsignedInteger('logo_size_bytes')->nullable();
            $table->timestamp('logo_updated_at')->nullable();

            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // ລຳດັບທີ່ໜ້າຈັດການ ແລະ dropdown ດຶງສະເໝີ
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};

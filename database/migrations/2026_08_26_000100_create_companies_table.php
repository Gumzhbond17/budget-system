<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();          // ໃຊ້ໃນ URL ແທນ id (ກັນ enumeration ຄືກັບ projects)

            // ---------- ໂຄງສ້າງຕົ້ນໄມ້ (ສຳນັກງານໃຫຍ່ → ສາຂາ → ຄູ່ຮ່ວມງານ) ----------
            $table->foreignId('parent_id')->nullable()->constrained('companies')->nullOnDelete();

            // Materialized path "/1/4/9/" — ຫາລູກຫຼານທັງໝົດດ້ວຍ LIKE '/1/%' ເທື່ອດຽວ
            // ແທນທີ່ຈະ recursive CTE (MySQL 8 ເຮັດໄດ້ ແຕ່ຊ້າກວ່າ ແລະ ອ່ານຍາກກວ່າ).
            // Trade-off: ຕ້ອງຂຽນ path ຄືນເມື່ອຍ້າຍ node — ເກີດຂຶ້ນໜ້ອຍຫຼາຍ.
            $table->string('path', 255)->default('/');
            $table->unsignedTinyInteger('depth')->default(0);

            $table->string('code', 30)->unique();    // SSS, SSS-VTE, PSG
            $table->string('name');                  // ບໍລິສັດ ແສງສະຫວ່າງ ຈຳກັດ
            $table->string('name_en')->nullable();

            // internal   = ອົງກອນເຈົ້າຂອງລະບົບ
            // branch     = ສາຂາ/ໜ່ວຍງານພາຍໃນ
            // third_party = ອົງກອນພາຍນອກທີ່ມາໃຊ້ລະບົບ (ຕ້ອງມີ trial)
            $table->enum('type', ['internal', 'branch', 'third_party'])->default('internal');

            // ---------- ອາຍຸການໃຊ້ງານ ----------
            // ຂໍ້ຕົກລົງ: ອາຍຸ **ຫຼັກ** ຢູ່ລະດັບບໍລິສັດ —
            // ອົງກອນໜຶ່ງໝົດອາຍຸ = ພະນັກງານທັງໝົດຂອງອົງກອນນັ້ນເຂົ້າບໍ່ໄດ້ພ້ອມກັນ,
            // ຕໍ່ອາຍຸບ່ອນດຽວປົດລັອກໃຫ້ທຸກຄົນ.
            //
            // ຕໍ່ມາ (2026-09-14) ເພີ່ມ `users.access_expires_at` ໄວ້ຫຍໍ້ອາຍຸລາຍຄົນ
            // ໃຫ້ສັ້ນກວ່ານີ້ໄດ້ ແຕ່ **ຢືດໃຫ້ຍາວກວ່າບໍ່ໄດ້** — ບ່ອນນີ້ຍັງເປັນເພດານຢູ່.
            $table->enum('plan', ['trial', 'active', 'suspended'])->default('trial');
            $table->date('trial_ends_at')->nullable();      // ຕັ້ງອັດຕະໂນມັດ = ວັນສ້າງ + config('company.trial_days')
            $table->date('access_expires_at')->nullable();  // null = ບໍ່ໝົດອາຍຸ (ໃຊ້ກັບ plan = active)

            // ---------- ຂໍ້ມູນຕິດຕໍ່ ----------
            $table->string('tax_id', 40)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 255)->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();   // ບໍລິສັດມີໂຄງການ+ການເງິນຜູກຢູ່ — ຫ້າມລຶບຖາວອນ

            $table->index('path');                    // ຄົ້ນຫາລູກຫຼານ
            $table->index(['parent_id', 'name']);     // ສະແດງເປັນຕົ້ນໄມ້ຕາມລຳດັບຊື່
            $table->index(['plan', 'access_expires_at']); // ຫາອົງກອນທີ່ໃກ້ໝົດອາຍຸ
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

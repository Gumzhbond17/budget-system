<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- projects ----------
        Schema::table('projects', function (Blueprint $table) {
            // ຍັງ nullable ຢູ່ໃນຂັ້ນນີ້ — ແຖວທີ່ມີຢູ່ແລ້ວຍັງບໍ່ມີບໍລິສັດ
            $table->foreignId('company_id')->nullable()->after('uuid')
                ->constrained('companies')->restrictOnDelete();

            $table->index(['company_id', 'status'], 'idx_company_status');
        });

        // ຍ້າຍໂຄງການທີ່ມີຢູ່ແລ້ວເຂົ້າບໍລິສັດເລີ່ມຕົ້ນ ເພື່ອໃຫ້ບັງຄັບ NOT NULL ໄດ້
        if (DB::table('projects')->whereNull('company_id')->exists()) {
            $companyId = $this->defaultCompanyId();

            DB::table('projects')->whereNull('company_id')->update(['company_id' => $companyId]);
        }

        // ບັດນີ້ບໍ່ມີແຖວໃດເປັນ null ແລ້ວ → ບັງຄັບໃຫ້ທຸກໂຄງການຕ້ອງສັງກັດບໍລິສັດ
        // (ນີ້ຄືສິ່ງທີ່ເຮັດໃຫ້ຂັ້ນຕອນ "ເລືອກບໍລິສັດ → ເລືອກໂຄງການ" ຮັບປະກັນໄດ້ໃນລະດັບ DB)
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
        });

        // ລະຫັດໂຄງການ unique **ຕໍ່ອົງກອນ** ບໍ່ແມ່ນທົ່ວລະບົບ.
        // ເດີມເປັນ unique ທົ່ວລະບົບ ເຊິ່ງແປວ່າອົງກອນ ຂ ໃຊ້ລະຫັດ "SOLAR-1" ບໍ່ໄດ້
        // ຖ້າອົງກອນ ກ ຈອງໄປກ່ອນ — ແລະ ຍັງເປັນຊ່ອງຮົ່ວທີ່ໃຫ້ເດົາໄດ້ວ່າອົງກອນອື່ນ
        // ມີໂຄງການລະຫັດຫຍັງແດ່ ຜ່ານຂໍ້ຄວາມ validation.
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique('projects_code_unique');
            $table->unique(['company_id', 'code'], 'projects_company_code_unique');
        });

        // ---------- users ----------
        Schema::table('users', function (Blueprint $table) {
            // ຜູ້ໃຊ້ສັງກັດອົງກອນໃດ — ອາຍຸການໃຊ້ງານຂອງອົງກອນນັ້ນຄວບຄຸມຜູ້ໃຊ້ຄົນນີ້.
            // null = ຜູ້ດູແລລະບົບ (platform admin) ທີ່ບໍ່ສັງກັດອົງກອນໃດ.
            $table->foreignId('company_id')->nullable()->after('id')
                ->constrained('companies')->nullOnDelete();

            // ຜູ້ດູແລລະບົບ — ຄົນດຽວທີ່ CRUD ບໍລິສັດ/ຜູ້ໃຊ້ໄດ້ ແລະ ບໍ່ຖືກຈຳກັດດ້ວຍອາຍຸ
            $table->boolean('is_platform_admin')->default(false)->after('password');

            // ປິດບັນຊີລາຍຄົນໂດຍບໍ່ຕ້ອງລຶບ (ພະນັກງານລາອອກ)
            $table->boolean('is_active')->default(true)->after('is_platform_admin');

            $table->timestamp('last_login_at')->nullable();

            $table->index(['company_id', 'is_active']);
        });

        // ຜູ້ໃຊ້ທີ່ມີຢູ່ກ່ອນມີແນວຄິດ "ອົງກອນ" ຕ້ອງຖືກຈັດເຂົ້າອົງກອນເລີ່ມຕົ້ນ —
        // ບໍ່ດັ່ງນັ້ນ middleware `access.active` ຈະລັອກທຸກຄົນອອກຈາກລະບົບທັນທີ
        // ຫຼັງ migrate (company_id = null ແປວ່າ "ຍັງບໍ່ໄດ້ຈັດສັນ").
        if (DB::table('users')->exists()) {
            $companyId = $this->defaultCompanyId();

            DB::table('users')->whereNull('company_id')->update(['company_id' => $companyId]);

            // ຄົນທຳອິດຂອງລະບົບກາຍເປັນຜູ້ດູແລລະບົບ — ຕ້ອງມີຢ່າງໜ້ອຍ 1 ຄົນ
            // ທີ່ເຂົ້າໜ້າຕັ້ງຄ່າໄດ້ ບໍ່ດັ່ງນັ້ນຈະບໍ່ມີໃຜສ້າງອົງກອນທຳອິດໄດ້ເລີຍ.
            $firstUserId = DB::table('users')->orderBy('id')->value('id');

            if ($firstUserId !== null) {
                DB::table('users')->where('id', $firstUserId)->update(['is_platform_admin' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['is_platform_admin', 'is_active', 'last_login_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique('projects_company_code_unique');
            $table->unique('code', 'projects_code_unique');
            $table->dropIndex('idx_company_status');
            $table->dropConstrainedForeignId('company_id');
        });
    }

    /** ບໍລິສັດເລີ່ມຕົ້ນສຳລັບຂໍ້ມູນເກົ່າທີ່ສ້າງກ່ອນມີແນວຄິດ "ບໍລິສັດ" */
    private function defaultCompanyId(): int
    {
        $existing = DB::table('companies')->where('code', 'DEFAULT')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $id = (int) DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid7(),
            'parent_id' => null,
            'path' => '/',
            'depth' => 0,
            'code' => 'DEFAULT',
            'name' => 'ອົງກອນເລີ່ມຕົ້ນ',
            'name_en' => 'Default Organisation',
            'type' => 'internal',
            'plan' => 'active',     // ຂໍ້ມູນເກົ່າຫ້າມຖືກລັອກດ້ວຍ trial ທີ່ໝົດອາຍຸແລ້ວ
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('companies')->where('id', $id)->update(['path' => "/{$id}/"]);

        return $id;
    }
};

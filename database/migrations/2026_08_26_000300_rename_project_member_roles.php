<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ປ່ຽນຊື່ role ໃຫ້ກົງກັບພາສາທີ່ຜູ້ໃຊ້ເຂົ້າໃຈ (ບໍ່ໄດ້ປ່ຽນຄວາມໝາຍ ຫຼື ສິດ):
 *
 *   owner      → full_access   ເຂົ້າເຖິງເຕັມ
 *   approver   → authorize     ອະນຸມັດ
 *   accountant → modify        ແກ້ໄຂ
 *   viewer     → read_only     ອ່ານຢ່າງດຽວ
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const RENAMES = [
        'owner' => 'full_access',
        'approver' => 'authorize',
        'accountant' => 'modify',
        'viewer' => 'read_only',
    ];

    public function up(): void
    {
        // enum → string: role ກາຍເປັນຂໍ້ມູນທີ່ຜູ້ໃຊ້ເລືອກເອງໃນໜ້າຕັ້ງຄ່າແລ້ວ.
        // ການເພີ່ມ role ໃໝ່ບໍ່ຄວນຕ້ອງ ALTER TABLE (ໃນ MySQL = ລັອກຕາຕະລາງ)
        // ແລະ enum ຂອງ MySQL ກັບ SQLite ປ່ຽນຄ່າຄົນລະທ່າ ເຮັດໃຫ້ migration ບໍ່ພົກພາ.
        // ຄ່າທີ່ອະນຸຍາດຖືກບັງຄັບຢູ່ ProjectMember::ROLES + FormRequest ແທນ.
        Schema::table('project_members', function (Blueprint $table) {
            $table->string('role', 20)->default('read_only')->change();
        });

        foreach (self::RENAMES as $old => $new) {
            DB::table('project_members')->where('role', $old)->update(['role' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('project_members')->where('role', $new)->update(['role' => $old]);
        }

        Schema::table('project_members', function (Blueprint $table) {
            $table->enum('role', ['owner', 'approver', 'accountant', 'viewer'])
                ->default('viewer')
                ->change();
        });
    }
};

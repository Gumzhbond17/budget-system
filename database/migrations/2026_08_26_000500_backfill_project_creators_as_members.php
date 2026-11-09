<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ໂຄງການທີ່ສ້າງກ່ອນໜ້ານີ້ເກີດມາໂດຍບໍ່ມີສະມາຊິກເລີຍ — ຜູ້ສ້າງເອງກໍເຂົ້າບໍ່ໄດ້.
 *
 * ExpensePolicy ແລະ RevenuePolicy ບັງຄັບການເປັນສະມາຊິກ ໂດຍບໍ່ມີທາງຂ້າມ
 * ສຳລັບຜູ້ດູແລລະບົບ (ຕັ້ງໃຈ — ຂໍ້ມູນການເງິນ). ຜົນຄື ໜ້າ "ລາຍຮັບ/ລາຍຈ່າຍ"
 * ຕອບ 403 ແລະ ປຸ່ມ "ເພີ່ມລາຍການ" ບໍ່ຂຶ້ນ ໂດຍບໍ່ບອກເຫດຜົນ.
 *
 * ຕັ້ງແຕ່ນີ້ໄປ ProjectController::store ຈະລົງທະບຽນຜູ້ສ້າງເປັນ full_access
 * ໃຫ້ອັດຕະໂນມັດ. migration ນີ້ແກ້ຂໍ້ມູນເກົ່າໃຫ້ກົງກັນ.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('projects')
            ->whereNull('deleted_at')
            ->whereNotNull('created_by')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('project_members')
                ->whereColumn('project_members.project_id', 'projects.id'))
            ->get(['id', 'created_by']);

        if ($orphans->isEmpty()) {
            return;
        }

        $now = now();

        DB::table('project_members')->insert(
            $orphans->map(fn ($project) => [
                'project_id' => $project->id,
                'user_id' => $project->created_by,
                'role' => 'full_access',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    /**
     * ບໍ່ຖອນຄືນ: ແຍກບໍ່ອອກວ່າແຖວໃດມາຈາກ migration ນີ້ ແລະ ແຖວໃດຜູ້ໃຊ້
     * ເພີ່ມເອງພາຍຫຼັງ. ການເດົາຜິດ = ຖອດຄົນອອກຈາກໂຄງການຂອງຕົນ.
     */
    public function down(): void {}
};

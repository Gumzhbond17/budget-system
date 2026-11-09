<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    /**
     * ຜູ້ດູແລລະບົບຈັດການໂຄງການຂອງທຸກອົງກອນໄດ້ — ຈຳເປັນເພາະລາວຄືຄົນທີ່
     * ຕັ້ງໂຄງການໃຫ້ອົງກອນໃໝ່ ກ່ອນທີ່ອົງກອນນັ້ນຈະມີສະມາຊິກຄົນທຳອິດ.
     *
     * ⚠️ `delete` ຍົກເວັ້ນ — ເງື່ອນໄຂ "ຍັງບໍ່ມີລາຍຈ່າຍ" ຕ້ອງມີຜົນກັບທຸກຄົນ.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return null;
        }

        return $user->is_platform_admin ? true : null;
    }

    /** ລາຍການຖືກ scope ໃນ controller ໃຫ້ເຫັນສະເພາະໂຄງການທີ່ຕົນເປັນສະມາຊິກ */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isMemberOf($project->id);
    }

    /** ສ້າງໂຄງການໃໝ່ = ຜູ້ດູແລລະບົບເທົ່ານັ້ນ (ຜ່ານ before) */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project->id, ['full_access']);
    }

    /**
     * ໂຄງການມີຂໍ້ມູນການເງິນຜູກຢູ່ — ລຶບໄດ້ສະເພາະເມື່ອຍັງບໍ່ມີລາຍຈ່າຍ
     * (ຕາຕະລາງໃຊ້ softDeletes ຢູ່ແລ້ວ ແຕ່ນີ້ຄືການກັນຄວາມຜິດພາດຂອງຄົນ)
     */
    public function delete(User $user, Project $project): bool
    {
        return ($user->is_platform_admin || $user->hasProjectRole($project->id, ['full_access']))
            && ! $project->expenses()->exists();
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project->id, ['full_access']);
    }
}

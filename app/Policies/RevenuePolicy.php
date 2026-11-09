<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\Revenue;
use App\Models\User;

/**
 * ກົດດຽວກັນກັບ ExpensePolicy — ເງິນເຂົ້າຕ້ອງຄຸມເຂັ້ມເທົ່າເງິນອອກ.
 *
 * ເປັນຫຍັງບໍ່ຜ່ອນຜັນ: ລາຍຮັບປອມເຮັດໃຫ້ "ຍອດຄົງເຫຼືອ" ເບິ່ງຄືດີກວ່າຄວາມຈິງ
 * ເຊິ່ງອັນຕະລາຍພໍກັນກັບລາຍຈ່າຍທີ່ບໍ່ມີຫຼັກຖານ.
 *
 *   1. ຕ້ອງເປັນສະມາຊິກໂຄງການກ່ອນຈຶ່ງເຫັນຫຍັງໄດ້
 *   2. ລາຍການ approved = lock — ປົດໄດ້ທາງດຽວຄື `reopen()` (ຜູ້ດູແລລະບົບ)
 *   3. ຜູ້ສ້າງອະນຸມັດເອງບໍ່ໄດ້ (segregation of duties)
 */
final class RevenuePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->isMemberOf($project->id);
    }

    public function view(User $user, Revenue $revenue): bool
    {
        return $user->isMemberOf($revenue->project_id);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project->id, ['full_access', 'modify']);
    }

    public function update(User $user, Revenue $revenue): bool
    {
        if ($revenue->isLocked()) {
            return false;
        }

        return $user->isMemberOf($revenue->project_id)
            && ($revenue->created_by === $user->id
                || $user->hasProjectRole($revenue->project_id, ['full_access', 'modify']));
    }

    public function delete(User $user, Revenue $revenue): bool
    {
        if ($revenue->isLocked()) {
            return false;
        }

        return $user->hasProjectRole($revenue->project_id, ['full_access', 'modify']);
    }

    /**
     * ຮ່າງ **ແລະ** ລາຍການທີ່ຖືກປະຕິເສດ ສົ່ງອະນຸມັດໄດ້ — ເຫດຜົນດຽວກັນກັບ
     * ExpensePolicy::submit(): RevenueService ຮັບ 2 ສະຖານະນີ້ຢູ່ແລ້ວ ແລະ
     * ຖ້າມີແຕ່ `draft` ການປະຕິເສດຈະກາຍເປັນທາງຕັນ.
     */
    public function submit(User $user, Revenue $revenue): bool
    {
        return in_array($revenue->status, ['draft', 'rejected'], true)
            && $this->update($user, $revenue);
    }

    public function approve(User $user, Revenue $revenue): bool
    {
        return $revenue->status === 'submitted'
            && $revenue->created_by !== $user->id
            && $user->hasProjectRole($revenue->project_id, ['full_access', 'authorize']);
    }

    public function reject(User $user, Revenue $revenue): bool
    {
        return $this->approve($user, $revenue);
    }

    /** ເບິ່ງ ExpensePolicy::reopen() — ກົດດຽວກັນ */
    public function reopen(User $user, Revenue $revenue): bool
    {
        return $revenue->status === 'approved'
            && $user->is_platform_admin
            && $user->isMemberOf($revenue->project_id);
    }

    public function attach(User $user, Revenue $revenue): bool
    {
        return $this->update($user, $revenue);
    }
}

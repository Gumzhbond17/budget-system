<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\Project;
use App\Models\User;

/**
 * ຫົວໃຈຂອງການປ້ອງກັນ IDOR — docs/architecture.md ພາກ 9.
 *
 * ກົດ 3 ຂໍ້ທີ່ບໍ່ມີວິທີໃດຂ້າມໄດ້:
 *   1. ຕ້ອງເປັນສະມາຊິກໂຄງການກ່ອນຈຶ່ງເຫັນຫຍັງໄດ້
 *   2. ລາຍການ approved = lock — ແກ້/ລຶບບໍ່ໄດ້. ທາງດຽວທີ່ປົດໄດ້ຄື `reopen()`
 *      (ຜູ້ດູແລລະບົບສົ່ງກັບໄປເປັນ rejected ພ້ອມເຫດຜົນ ແລະ ບັນທຶກໃນ audit log)
 *   3. ຜູ້ສ້າງອະນຸມັດເອງບໍ່ໄດ້ (segregation of duties)
 */
final class ExpensePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->isMemberOf($project->id);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->isMemberOf($expense->project_id);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasProjectRole($project->id, ['full_access', 'modify']);
    }

    public function update(User $user, Expense $expense): bool
    {
        // ອະນຸມັດແລ້ວ = lock ຖາວອນ ບໍ່ວ່າໃຜກໍແກ້ບໍ່ໄດ້
        if ($expense->isLocked()) {
            return false;
        }

        return $user->isMemberOf($expense->project_id)
            && ($expense->created_by === $user->id
                || $user->hasProjectRole($expense->project_id, ['full_access', 'modify']));
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($expense->isLocked()) {
            return false;
        }

        return $user->hasProjectRole($expense->project_id, ['full_access', 'modify']);
    }

    /**
     * ຮ່າງ **ແລະ** ລາຍການທີ່ຖືກປະຕິເສດ ສົ່ງອະນຸມັດໄດ້.
     *
     * `rejected` ຕ້ອງຢູ່ໃນນີ້: ExpenseService::submit() ຮັບ 2 ສະຖານະນີ້ ແລະ
     * ແຜນຜັງທີ່ ExpenseWorkflowController ຂຽນໄວ້ກໍລະບຸ `rejected → submitted`.
     * ຖ້າມີແຕ່ `draft` ລາຍການທີ່ຖືກປະຕິເສດຈະຄ້າງຕາຍ — ແກ້ໄດ້ ແຕ່ສົ່ງຄືນບໍ່ໄດ້
     * ຈົນຕະຫຼອດ ເຊິ່ງເຮັດໃຫ້ການປະຕິເສດກາຍເປັນທາງຕັນ.
     */
    public function submit(User $user, Expense $expense): bool
    {
        return in_array($expense->status, ['draft', 'rejected'], true)
            && $this->update($user, $expense);
    }

    public function approve(User $user, Expense $expense): bool
    {
        // ຜູ້ສ້າງອະນຸມັດເອງບໍ່ໄດ້ — segregation of duties
        return $expense->status === 'submitted'
            && $expense->created_by !== $user->id
            && $user->hasProjectRole($expense->project_id, ['full_access', 'authorize']);
    }

    public function reject(User $user, Expense $expense): bool
    {
        return $this->approve($user, $expense);
    }

    /**
     * ຍົກເລີກການອະນຸມັດ ແລ້ວສົ່ງກັບໃຫ້ຜູ້ບັນທຶກແກ້ (approved → rejected).
     *
     * ສະເພາະຜູ້ດູແລລະບົບ (`is_platform_admin`) ທີ່ເປັນສະມາຊິກໂຄງການ — ຍັງ
     * ຢູ່ໃຕ້ກົດຂໍ້ 1: ບໍ່ມີ before() bypass, ບໍ່ເຫັນລາຍການກໍປົດບໍ່ໄດ້.
     * ຫຼັງສົ່ງກັບ ລາຍການຜ່ານຂັ້ນຕອນປົກກະຕິຄືນໝົດ: ຜູ້ບັນທຶກແກ້ → ສົ່ງອະນຸມັດ →
     * ຜູ້ອະນຸມັດທີ່ບໍ່ແມ່ນຜູ້ສ້າງອະນຸມັດ.
     */
    public function reopen(User $user, Expense $expense): bool
    {
        return $expense->status === 'approved'
            && $user->is_platform_admin
            && $user->isMemberOf($expense->project_id);
    }

    /** ແນບຫຼັກຖານໄດ້ຕາບໃດທີ່ຍັງແກ້ລາຍການໄດ້ (ພາກ 4.2 StoreAttachmentRequest) */
    public function attach(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}

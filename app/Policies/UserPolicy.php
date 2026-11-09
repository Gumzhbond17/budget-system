<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * ຜູ້ໃຊ້ເປັນຂໍ້ມູນລະດັບແພລດຟອມ — ມີແຕ່ຜູ້ດູແລລະບົບທີ່ຈັດການໄດ້.
 *
 * ໝາຍເຫດ: ການ **ມອບສິດເຂົ້າໂຄງການ** ບໍ່ໄດ້ຢູ່ນີ້ — ຢູ່ທີ່
 * ProjectPolicy::manageMembers ເພື່ອໃຫ້ເຈົ້າຂອງໂຄງການເພີ່ມຄົນເອງໄດ້
 * ໂດຍບໍ່ຕ້ອງເປັນຜູ້ດູແລລະບົບ.
 */
final class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return null;
        }

        return $user->is_platform_admin ? true : null;
    }

    /** ລາຍການຖືກ scope ໃນ controller ໃຫ້ເຫັນສະເພາະເພື່ອນຮ່ວມອົງກອນ */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model)
            || ($user->company_id !== null && $user->company_id === $model->company_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    /**
     * ຮູບໂປຣໄຟລ໌: ທຸກຄົນປ່ຽນຂອງຕົນເອງໄດ້ — ບໍ່ຕ້ອງເປັນຜູ້ດູແລລະບົບ.
     * (ຜູ້ດູແລຜ່ານ before() ໄດ້ຢູ່ແລ້ວ ແຕ່ endpoint ເຮັດວຽກກັບ "ຕົນເອງ" ເທົ່ານັ້ນ)
     */
    public function updateAvatar(User $user, User $model): bool
    {
        return $user->is($model);
    }

    /**
     * ຜູ້ໃຊ້ຜູກກັບລາຍຈ່າຍຜ່ານ `expenses.created_by` (ບໍ່ແມ່ນ nullOnDelete) —
     * ລຶບຖາວອນຈະພັງ FK. ໃຊ້ is_active = false ແທນ.
     *
     * ຫ້າມລຶບຕົນເອງ ເພື່ອກັນຜູ້ດູແລຄົນສຸດທ້າຍລັອກຕົນເອງອອກຈາກລະບົບ.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->is_platform_admin
            && ! $user->is($model)
            && $model->projectMemberships()->doesntExist();
    }
}

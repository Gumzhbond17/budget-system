<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy ຮ່ວມຂອງ master data: ໝວດລາຍຈ່າຍ, ຜູ້ຈ່າຍ, ຜູ້ຮັບເງິນ, ອັດຕາແລກປ່ຽນ.
 *
 * ຂໍ້ມູນເຫຼົ່ານີ້ **ໃຊ້ຮ່ວມກັນທຸກໂຄງການ** ຈຶ່ງ scope ດ້ວຍ `project_members` ບໍ່ໄດ້
 * ຄືກັບ ExpensePolicy. ກົດຈຶ່ງເປັນ:
 *
 *   ອ່ານ  — ຜູ້ໃຊ້ທີ່ login ແລ້ວທຸກຄົນ (Vue ຕ້ອງໃຊ້ເຕີມ dropdown)
 *   ຂຽນ  — ຕ້ອງເປັນ owner ຫຼື accountant ຢູ່ໂຄງການໃດໜຶ່ງ
 *
 * ⚠️ `delete` ໃຫ້ສິດຢູ່ນີ້ ແຕ່ "ລຶບໄດ້ບໍ" ຍັງຂຶ້ນກັບວ່າມີໃຜອ້າງເຖິງແຖວນັ້ນຢູ່ບໍ
 *    ເຊິ່ງເປັນເລື່ອງຂອງຂໍ້ມູນ ບໍ່ແມ່ນເລື່ອງສິດ — controller ກວດ ແລ້ວຕອບ 409.
 *    ປັດຈຸບັນມີແຕ່ໝວດລາຍຈ່າຍທີ່ເປີດ route ລຶບ (ເບິ່ງ CategoryController).
 */
final class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyProjectRole(['full_access', 'modify']);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasAnyProjectRole(['full_access', 'modify']);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasAnyProjectRole(['full_access', 'modify']);
    }
}

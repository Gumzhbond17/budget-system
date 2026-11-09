<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * ບັນທຶກການໃຊ້ງານ — ຜູ້ດູແລລະບົບເທົ່ານັ້ນ.
 *
 * log ຂ້າມທຸກອົງກອນ ແລະ ມີ IP, browser, ອີເມວທີ່ login ລົ້ມເຫຼວ ແລະ ຄຳທີ່ຄົນອື່ນ
 * ຄົ້ນຫາ — ຂໍ້ມູນສ່ວນຕົວຂອງຜູ້ໃຊ້ຄົນອື່ນ ບໍ່ແມ່ນຂໍ້ມູນຂອງໂຄງການ.
 *
 * ບໍ່ມີ create/update/delete: ແຖວ log ຂຽນໄດ້ແຕ່ລະບົບ ແລະ ແກ້/ລຶບບໍ່ໄດ້.
 */
final class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $user->is_platform_admin;
    }
}

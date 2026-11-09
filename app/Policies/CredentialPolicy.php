<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Credential;
use App\Models\User;

/**
 * ບັນຊີເຂົ້າໃຊ້ລະບົບ — **ຜູ້ດູແລລະບົບເທົ່ານັ້ນ** ທັງອ່ານ ແລະ ຂຽນ.
 *
 * ຕ່າງຈາກ MasterDataPolicy ທີ່ໃຫ້ທຸກຄົນອ່ານໄດ້ (Vue ຕ້ອງໃຊ້ເຕີມ dropdown):
 * ບໍ່ມີໜ້າໃດນອກຈາກໜ້າຈັດການນີ້ທີ່ຕ້ອງການຂໍ້ມູນນີ້ ຈຶ່ງບໍ່ມີເຫດຜົນໃຫ້ຜູ້ໃຊ້
 * ທຳມະດາເຫັນແມ່ນແຕ່ລາຍຊື່ວ່າອົງກອນມີບັນຊີຂອງລະບົບໃດຢູ່ແດ່.
 *
 * ⚠️ ບໍ່ໄດ້ໃຊ້ `before()` ຄື UserPolicy — ຂຽນເງື່ອນໄຂດຽວກັນທຸກ method ແທນ
 * ເພື່ອບໍ່ໃຫ້ມີທາງລັດທີ່ຜ່ານໄປໄດ້ໂດຍບໍ່ຕັ້ງໃຈເມື່ອເພີ່ມ ability ໃໝ່ພາຍຫຼັງ.
 */
final class CredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function view(User $user, Credential $credential): bool
    {
        return $user->is_platform_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function update(User $user, Credential $credential): bool
    {
        return $user->is_platform_admin;
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->is_platform_admin;
    }

    /**
     * ເປີດເບິ່ງລະຫັດຈິງ.
     *
     * ແຍກຈາກ `view` ໂດຍເຈດຕະນາ: `view` ຄື "ເຫັນແຖວ" ສ່ວນອັນນີ້ຄື "ເຫັນ
     * ຄວາມລັບ". ປັດຈຸບັນທັງສອງຕ້ອງການສິດເທົ່າກັນ ແຕ່ການແຍກໄວ້ເຮັດໃຫ້ຈຳກັດ
     * ອັນໜຶ່ງໃຫ້ແຄບລົງໄດ້ພາຍຫຼັງ ໂດຍບໍ່ຕ້ອງແກ້ທຸກບ່ອນທີ່ເອີ້ນ.
     *
     * ⚠️ ສິດຢູ່ນີ້ບໍ່ພຽງພໍ — controller ຍັງບັງຄັບໃຫ້ພິມລະຫັດຜ່ານຂອງຕົນເອງ
     * ຢືນຢັນອີກຊັ້ນ (ກັນຄົນທີ່ຍ່າງມາໃຊ້ໜ້າຈໍທີ່ເປີດຄ້າງໄວ້).
     */
    public function reveal(User $user, Credential $credential): bool
    {
        return $user->is_platform_admin;
    }
}

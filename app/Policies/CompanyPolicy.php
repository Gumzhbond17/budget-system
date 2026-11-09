<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * ອົງກອນເປັນຂໍ້ມູນລະດັບແພລດຟອມ — ມີແຕ່ຜູ້ດູແລລະບົບທີ່ສ້າງ/ແກ້/ລຶບໄດ້.
 *
 * ເຫດຜົນ: ຖ້າຜູ້ໃຊ້ຂອງອົງກອນໜຶ່ງແກ້ຂໍ້ມູນອົງກອນຕົນເອງໄດ້ ກໍຈະຕໍ່ອາຍຸ trial
 * ໃຫ້ຕົນເອງໄດ້ນຳ — ນັ້ນເຮັດໃຫ້ການຈຳກັດອາຍຸບໍ່ມີຄວາມໝາຍ.
 */
final class CompanyPolicy
{
    /**
     * ຜູ້ດູແລລະບົບຜ່ານທຸກຂໍ້ — ກວດກ່ອນ method ອື່ນທັງໝົດ.
     * ຄືນ null = "ບໍ່ຕັດສິນ" ປ່ອຍໃຫ້ method ລຸ່ມນີ້ຕັດສິນຕໍ່.
     *
     * ⚠️ `delete` ບໍ່ຢູ່ໃນນີ້ໂດຍເຈດຕະນາ — ມັນມີເງື່ອນໄຂ "ຕ້ອງເປົ່າກ່ອນ"
     * ທີ່ຕ້ອງມີຜົນກັບຜູ້ດູແລລະບົບເໝືອນກັນ ບໍ່ດັ່ງນັ້ນ before() ຈະຂ້າມມັນໄປ.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return null;
        }

        return $user->is_platform_admin ? true : null;
    }

    /** ລາຍການຖືກ scope ໃນ controller ໃຫ້ເຫັນສະເພາະອົງກອນຂອງຕົນ */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id;
    }

    /**
     * ຜູ້ໃຊ້ທີ່ສັງກັດອົງກອນໃດໜຶ່ງ ສ້າງ **ສາຂາ/ຮ້ານ** ພາຍໃຕ້ອົງກອນຕົນໄດ້.
     *
     * ບໍ່ໄດ້ບັງຄັບວ່າຕ້ອງມີ role ໃນໂຄງການກ່ອນ ໂດຍເຈດຕະນາ: ຜູ້ໃຊ້ຄົນທຳອິດ
     * ຂອງອົງກອນໃໝ່ຍັງບໍ່ມີໂຄງການໃດເລີຍ ຖ້າບັງຄັບຈະຕິດວົງຈອນ — ສ້າງຮ້ານບໍ່ໄດ້
     * ເພາະບໍ່ມີ role, ແລະ ບໍ່ມີ role ເພາະຍັງບໍ່ມີຮ້ານ. ການສ້າງຮ້ານຈະມອບ
     * `full_access` ໃນໂຄງການຂອງຮ້ານນັ້ນໃຫ້ຜູ້ສ້າງເອງ (CompanyController).
     *
     * ຂອບເຂດຄວາມເສຍຫາຍຖືກຈຳກັດຢູ່ສອງຊັ້ນ:
     *   1. controller ບັງຄັບ type ∈ SELF_SERVICE_TYPES ແລະ parent ຢູ່ໃນຕົ້ນໄມ້ຕົນ
     *   2. controller ຕັດ plan / trial_ends_at / access_expires_at ອອກ
     *      — ຜູ້ໃຊ້ຈຶ່ງຕໍ່ອາຍຸໃຫ້ຕົນເອງບໍ່ໄດ້ ເຊິ່ງຄືເຫດຜົນເດີມຂອງ policy ນີ້
     */
    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    /** ແກ້ໄດ້ສະເພາະສາຂາ/ຮ້ານ ທີ່ຢູ່ໃນຕົ້ນໄມ້ຂອງຕົນ — ແຖວອົງກອນເອງແກ້ບໍ່ໄດ້ */
    public function update(User $user, Company $company): bool
    {
        return $this->managesAsOwnUnit($user, $company);
    }

    /**
     * ລຶບໄດ້ສະເພາະອົງກອນເປົ່າ — ຍັງມີສາຂາ, ໂຄງການ ຫຼື ຜູ້ໃຊ້ຢູ່ຫ້າມລຶບ.
     *
     * ນີ້ບໍ່ແມ່ນເລື່ອງສິດແຕ່ເປັນການກັນຄວາມຜິດພາດຂອງຄົນ: ຕາຕະລາງໃຊ້ softDeletes
     * ຢູ່ແລ້ວ ແຕ່ `projects.company_id` ເປັນ restrictOnDelete ຈຶ່ງຈະລົ້ມແບບ
     * SQL error ຖ້າບໍ່ດັກໄວ້ກ່ອນ. ໃຫ້ໃຊ້ is_active = false ແທນ.
     */
    public function delete(User $user, Company $company): bool
    {
        if (! $user->is_platform_admin && ! $this->managesAsOwnUnit($user, $company)) {
            return false;
        }

        if ($company->children()->exists() || $company->users()->exists()) {
            return false;
        }

        // ຮ້ານມີໂຄງການຂອງຕົນທີ່ລະບົບສ້າງໃຫ້ອັດຕະໂນມັດ ຈຶ່ງໃຊ້ກົດ
        // "ຕ້ອງບໍ່ມີໂຄງການ" ບໍ່ໄດ້ — ບໍ່ດັ່ງນັ້ນຮ້ານທີ່ຫາກໍສ້າງຈະລຶບບໍ່ໄດ້ເລີຍ.
        // ກົດທີ່ແທ້ຈິງຄື "ຕ້ອງຍັງບໍ່ມີເງິນເຄື່ອນໄຫວ".
        if ($company->type === 'store') {
            return ! $company->projects()
                ->where(fn ($q) => $q->whereHas('expenses')->orWhereHas('revenues'))
                ->exists();
        }

        return $company->projects()->doesntExist();
    }

    /**
     * ແຖວນີ້ເປັນ "ໜ່ວຍງານລູກຂອງອົງກອນຜູ້ໃຊ້" ບໍ.
     *
     * ສາມເງື່ອນໄຂພ້ອມກັນ: ຜູ້ໃຊ້ມີອົງກອນ, ແຖວເປົ້າໝາຍເປັນສາຂາ/ຮ້ານ (ບໍ່ແມ່ນ
     * ແຖວອົງກອນລະດັບເທິງ ທີ່ຖືແພັກເກັດ) ແລະ ຢູ່ໃນຕົ້ນໄມ້ດຽວກັນກັບຜູ້ໃຊ້.
     *
     * ປຽບທຽບດ້ວຍ `path` ຈຶ່ງກວດຄວາມເປັນລູກຫຼານທຸກຊັ້ນໄດ້ດ້ວຍ string ດຽວ
     * ບໍ່ຕ້ອງໄລ່ຂຶ້ນຕົ້ນໄມ້.
     */
    private function managesAsOwnUnit(User $user, Company $company): bool
    {
        if ($user->company_id === null) {
            return false;
        }

        if (! in_array($company->type, Company::SELF_SERVICE_TYPES, true)) {
            return false;
        }

        $own = $user->company;

        return $own !== null && str_starts_with($company->path, $own->path);
    }
}

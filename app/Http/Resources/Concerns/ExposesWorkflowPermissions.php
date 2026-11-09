<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ສົ່ງ "ຜູ້ໃຊ້ຄົນນີ້ເຮັດຫຍັງໄດ້ແດ່ກັບແຖວນີ້" ອອກໄປພ້ອມກັບຂໍ້ມູນ.
 *
 * ໃຊ້ໂດຍ ExpenseResource ແລະ RevenueResource — ສອງ workflow ນີ້ມີກົດດຽວກັນ
 * (draft → submitted → approved / rejected) ແລະ Policy ຂອງມັນກໍຄູ່ກັນແຖວຕໍ່ແຖວ.
 *
 * ເປັນຫຍັງບໍ່ໃຫ້ Vue ຄິດເອງຈາກ `status` + `my_role`:
 *   1. ກົດ "ຜູ້ສ້າງອະນຸມັດເອງບໍ່ໄດ້" ຕ້ອງໃຊ້ `created_by` ເຊິ່ງເປັນ id ພາຍໃນ
 *      ທີ່ບໍ່ເຄີຍສົ່ງອອກ (ພາກ 9 — ກັນ enumeration). ສົ່ງຜົນການກວດແທນ
 *      ຄ່າດິບ ໄດ້ຄຳຕອບຄົບໂດຍບໍ່ຮົ່ວຫຍັງ.
 *   2. ມີ logic ອະນຸຍາດບ່ອນດຽວ. ຖ້າ Vue ຄິດຄູ່ຂະໜານກັບ Policy ມື້ໃດມື້ໜຶ່ງ
 *      ສອງບ່ອນຈະບໍ່ກົງກັນ ແລ້ວຜູ້ໃຊ້ຈະເຫັນປຸ່ມທີ່ກົດແລ້ວໄດ້ 403.
 *
 * ຄ່າໃຊ້ຈ່າຍ: Policy ທຸກຂໍ້ຂອງສອງ workflow ນີ້ອ່ານ role ຜ່ານ
 * `User::projectRoles()` ເຊິ່ງ memoize ໄວ້ຕໍ່ request — ກວດ 5 ຂໍ້ × 25 ແຖວ
 * ບໍ່ຕີ DB ເພີ່ມແມ່ນແຕ່ query ດຽວ.
 *
 * @phpstan-require-extends JsonResource
 */
trait ExposesWorkflowPermissions
{
    /**
     * @return array{
     *     update: bool,
     *     delete: bool,
     *     submit: bool,
     *     approve: bool,
     *     reject: bool,
     *     reopen: bool,
     * }
     */
    protected function permissionsFor(Request $request): array
    {
        $user = $request->user();

        // ບໍ່ມີຜູ້ໃຊ້ = ບໍ່ໄດ້ຫຍັງ. ເກີດຂຶ້ນຈິງຢູ່ເສັ້ນທາງ PDF ທີ່ເປັນ `signed`
        // ບໍ່ແມ່ນ `auth:sanctum` (ເບິ່ງ ReportController) — ບ່ອນນັ້ນບໍ່ໃຊ້ຄ່ານີ້
        // ຢູ່ແລ້ວ ແລະ ການ short-circuit ຢູ່ນີ້ກັນ TypeError ພ້ອມກັບປະຫຍັດ
        // ການກວດ Policy ຕໍ່ແຖວທີ່ບໍ່ມີໃຜອ່ານ.
        if ($user === null) {
            return [
                'update' => false,
                'delete' => false,
                'submit' => false,
                'approve' => false,
                'reject' => false,
                'reopen' => false,
            ];
        }

        return [
            'update' => $user->can('update', $this->resource),
            'delete' => $user->can('delete', $this->resource),
            'submit' => $user->can('submit', $this->resource),
            'approve' => $user->can('approve', $this->resource),
            'reject' => $user->can('reject', $this->resource),
            // ຍົກເລີກການອະນຸມັດ — ຜູ້ດູແລລະບົບເທົ່ານັ້ນ (ExpensePolicy::reopen)
            'reopen' => $user->can('reopen', $this->resource),
        ];
    }
}

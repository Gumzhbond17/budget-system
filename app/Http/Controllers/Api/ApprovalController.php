<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PendingApprovalResource;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * ຄິວລໍຖ້າອະນຸມັດ — ແຫຼ່ງຂໍ້ມູນຂອງກະດິ່ງແຈ້ງເຕືອນ ແລະ ໜ້າ `/approvals`.
 *
 * ບໍ່ມີຕາຕະລາງ `notifications`: ລາຍການນີ້ **ຄິດສົດ** ຈາກສະຖານະຂອງທຸລະກຳ
 * ທຸກເທື່ອທີ່ຖາມ. ເຫດຜົນ —
 *   1. ບໍ່ມີວັນຄ້າງ. ຖ້າເກັບເປັນແຖວ ພໍຄົນອື່ນອະນຸມັດຕັດໜ້າໄປ ແຖວແຈ້ງເຕືອນ
 *      ຈະຍັງຢູ່ ແລ້ວຜູ້ໃຊ້ກົດເຂົ້າໄປພົບ 422 ໂດຍບໍ່ຮູ້ສາເຫດ.
 *   2. ສິດປ່ຽນໄດ້. ຖອດຄົນອອກຈາກໂຄງການ = ລາວຕ້ອງເຊົາເຫັນລາຍການນັ້ນທັນທີ
 *      ເຊິ່ງເກີດຂຶ້ນເອງເມື່ອຄິດສົດ ແຕ່ຕ້ອງໄລ່ລຶບຖ້າເກັບໄວ້.
 *   3. ບໍ່ຕ້ອງມີ observer, migration ຫຼື job ລ້າງຂໍ້ມູນ.
 *
 * ຄ່າໃຊ້ຈ່າຍ: 2 query ຕໍ່ການຖາມ 1 ເທື່ອ (ລາຍຈ່າຍ + ລາຍຮັບ) ບວກ eager load.
 * ຄິວແບບນີ້ມີບໍ່ເທົ່າໃດສິບແຖວ ຈຶ່ງບໍ່ຕ້ອງແບ່ງໜ້າ.
 */
final class ApprovalController extends Controller
{
    /**
     * ເພດານກັນ payload ບານ — ຄິວທີ່ຍາວກວ່ານີ້ແປວ່າມີບັນຫາຂະບວນການ
     * ບໍ່ແມ່ນບັນຫາການສະແດງຜົນ.
     */
    private const MAX_ROWS = 200;

    /** role ທີ່ອະນຸມັດໄດ້ — ຕ້ອງກົງກັບ ExpensePolicy/RevenuePolicy::approve */
    private const APPROVER_ROLES = ['full_access', 'authorize'];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            // ກະດິ່ງເອົາແຕ່ 5 ແຖວເທິງສຸດ ສ່ວນໜ້າ /approvals ເອົາທັງໝົດ.
            // `total` ຂ້າງລຸ່ມເປັນຈຳນວນເຕັມສະເໝີ ບໍ່ວ່າຈະຕັດເທົ່າໃດ —
            // ບໍ່ດັ່ງນັ້ນປ້າຍເລກເທິງກະດິ່ງຈະຄ້າງຢູ່ທີ່ 5
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ROWS],
        ]);

        $user = $request->user();

        $pending = $this->pendingFor($user);

        $total = $pending->count();

        if (isset($filters['limit'])) {
            $pending = $pending->take($filters['limit']);
        }

        return PendingApprovalResource::collection($pending->values())
            ->additional(['meta' => ['total' => $total]])
            ->response();
    }

    /**
     * ທຸກລາຍການທີ່ຜູ້ໃຊ້ຄົນນີ້ອະນຸມັດໄດ້ຕອນນີ້ ຮຽງໃໝ່ສຸດຂຶ້ນກ່ອນ.
     *
     * @return Collection<int, Expense|Revenue>
     */
    private function pendingFor(User $user): Collection
    {
        // ໂຄງການທີ່ຜູ້ໃຊ້ມີສິດອະນຸມັດ — ອ່ານຈາກ role ທີ່ memoize ໄວ້ແລ້ວ
        // (`User::projectRoles()`) ຈຶ່ງບໍ່ຕີ DB ເພີ່ມ
        $projectIds = array_keys(array_filter(
            $user->projectRoles(),
            fn (string $role): bool => in_array($role, self::APPROVER_ROLES, true),
        ));

        if ($projectIds === []) {
            return collect();
        }

        $expenses = Expense::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', 'submitted')
            // ຜູ້ສ້າງອະນຸມັດເອງບໍ່ໄດ້ — ກັ່ນຢູ່ SQL ເພື່ອບໍ່ດຶງແຖວທີ່ຈະຖືກຖິ້ມ
            ->where('created_by', '!=', $user->id)
            ->with(['project:id,uuid,code,name', 'creator:id,name'])
            ->latest('updated_at')
            ->limit(self::MAX_ROWS)
            ->get();

        $revenues = Revenue::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', 'submitted')
            ->where('created_by', '!=', $user->id)
            ->with(['project:id,uuid,code,name', 'creator:id,name'])
            ->latest('updated_at')
            ->limit(self::MAX_ROWS)
            ->get();

        return $this->merge($expenses, $revenues)
            /*
             * ຕາໜ່າງກັນຊັ້ນສຸດທ້າຍ — ຖາມ Policy ຈິງຕໍ່ແຖວ.
             *
             * ເງື່ອນໄຂ where ຂ້າງເທິງສະທ້ອນ `approve()` ຢູ່ແລ້ວ ແຕ່ຖ້າມື້ໃດ
             * Policy ເພີ່ມກົດ (ເຊັ່ນ "ຕ້ອງມີຫຼັກຖານກ່ອນຈຶ່ງອະນຸມັດໄດ້") ແລ້ວ
             * ລືມມາແກ້ບ່ອນນີ້ ຄິວຈະສະແດງແຖວທີ່ກົດແລ້ວໄດ້ 403.
             * ຖາມ Policy ຊື່ໆເສຍຄ່າໜ້ອຍ (role memoize ແລ້ວ) ແລະ ຮັບປະກັນວ່າ
             * ສອງບ່ອນຈະບໍ່ເລື່ອນອອກຈາກກັນ.
             */
            ->filter(fn (Expense|Revenue $entry): bool => $user->can('approve', $entry))
            ->values();
    }

    /**
     * ລວມສອງຊະນິດເປັນລາຍການດຽວ ຮຽງຕາມເວລາທີ່ສົ່ງມາ (ໃໝ່ສຸດກ່ອນ).
     *
     * @param  EloquentCollection<int, Expense>  $expenses
     * @param  EloquentCollection<int, Revenue>  $revenues
     * @return Collection<int, Expense|Revenue>
     */
    private function merge(EloquentCollection $expenses, EloquentCollection $revenues): Collection
    {
        return $expenses->toBase()
            ->concat($revenues->toBase())
            ->sortByDesc(fn (Expense|Revenue $entry): int => $entry->updated_at?->getTimestamp() ?? 0)
            ->take(self::MAX_ROWS);
    }
}

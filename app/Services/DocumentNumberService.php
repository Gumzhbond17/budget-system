<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Revenue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ອອກເລກທີ່ໃບສຳຄັນ: {ຄຳນຳໜ້າ}-{ປີ}-{ລຳດັບ 4 ຕົວ} ຕໍ່ໂຄງການ ຕໍ່ປີ.
 *
 *   ລາຍຈ່າຍ EXP-2026-0001
 *   ລາຍຮັບ  REV-2026-0001
 *
 * ສອງລຳດັບແຍກກັນ ບໍ່ໃຊ້ຮ່ວມ — ນັກບັນຊີອ່ານເລກແລ້ວຮູ້ທັນທີວ່າເປັນລາຍການປະເພດໃດ
 * ໂດຍບໍ່ຕ້ອງເປີດເບິ່ງ.
 *
 * ໃນ Excel ເດີມເລກລຳດັບຊ້ຳກັນ (ເລກ 49 ປາກົດ 2 ເທື່ອ — docs/architecture.md ພາກ 1.4)
 * ຈຶ່ງຕ້ອງໃຫ້ລະບົບອອກເລກເອງ ບໍ່ໃຫ້ຄົນພິມ.
 *
 * ⚠️ ຕ້ອງຖືກເອີ້ນພາຍໃນ DB transaction (ExpenseService/RevenueService ເປີດໃຫ້ແລ້ວ) —
 *    lockForUpdate ຈຶ່ງຈະກັນສອງ request ອອກເລກດຽວກັນ.
 */
final class DocumentNumberService
{
    public function nextFor(int $projectId, CarbonInterface $date): string
    {
        return $this->next(Expense::withTrashed(), 'EXP', $projectId, $date);
    }

    public function nextForRevenue(int $projectId, CarbonInterface $date): string
    {
        return $this->next(Revenue::withTrashed(), 'REV', $projectId, $date);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  ຕ້ອງລວມແຖວທີ່ soft-delete ແລ້ວ —
     *                                  ເລກທີ່ອອກໄປແລ້ວຫ້າມນຳກັບມາໃຊ້ຄືນ
     */
    private function next(Builder $query, string $prefix, int $projectId, CarbonInterface $date): string
    {
        $fullPrefix = sprintf('%s-%d-', $prefix, $date->year);

        $last = $query
            ->where('project_id', $projectId)
            ->where('doc_no', 'like', $fullPrefix.'%')
            ->orderByDesc('id')   // ເລກອອກຕາມລຳດັບການສ້າງ → id ຫຼ້າສຸດ = ເລກສູງສຸດ
            ->lockForUpdate()
            ->value('doc_no');

        $next = $last === null
            ? 1
            : ((int) substr((string) $last, strlen($fullPrefix))) + 1;

        return $fullPrefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

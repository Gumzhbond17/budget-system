<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Http\Resources\MoneyResource;
use App\Models\Project;
use App\Models\Revenue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use stdClass;

/**
 * ຄູ່ຂອງ {@see ExpenseReportService} ສຳລັບລາຍຮັບ.
 *
 * ຄິດຈາກ `amount_usd_minor` / `amount_lak_minor` ທີ່ຄິດໄວ້ລ່ວງໜ້າຄືກັນ ຈຶ່ງເປັນ
 * `SUM()` ທຳມະດາເໜືອ index (ພາກ 2.1.ຄ).
 *
 * ຄວາມຕ່າງຈາກລາຍຈ່າຍທີ່ຕ້ອງຮູ້:
 *   - ລາຍຮັບບໍ່ມີມິຕິ "ໝວດ" (expense_categories ເປັນຮູບຮ່າງຂອງລາຍຈ່າຍ)
 *   - `payer_id` ຢູ່ນີ້ໝາຍເຖິງ **ແຫຼ່ງທຶນ** ຄືໃຜເອົາເງິນເຂົ້າ
 *   - ວັນທີຄື `received_date` ບໍ່ແມ່ນ `expense_date`
 */
final class RevenueReportService
{
    /**
     * ຍອດລວມ + ຈຳນວນລາຍການແຍກຕາມສະຖານະ.
     *
     * `by_status` ນັບທຸກສະຖານະສະເໝີ (ບໍ່ຂຶ້ນກັບ filter ສະຖານະ) ເພື່ອໃຫ້ໜ້າຈໍບອກໄດ້
     * ວ່າ "ຍັງມີ 3 ລາຍການລໍຖ້າອະນຸມັດ" — ບໍ່ດັ່ງນັ້ນຄົນຈະບໍ່ຮູ້ວ່າຍອດທີ່ເຫັນຂາດຫຍັງໄປ.
     *
     * @return array<string, mixed>
     */
    public function summary(Project $project, ReportFilters $filters): array
    {
        $totals = $this->scopedQuery($project, $filters)
            ->toBase()
            ->selectRaw('COUNT(*) as item_count')
            ->selectRaw('COALESCE(SUM(amount_usd_minor), 0) as total_usd_minor')
            ->selectRaw('COALESCE(SUM(amount_lak_minor), 0) as total_lak_minor')
            ->first();

        /** @var array<string, stdClass> $byStatus */
        $byStatus = $this->scopedQuery($project, $filters, withStatus: false)
            ->toBase()
            ->selectRaw('status')
            ->selectRaw('COUNT(*) as item_count')
            ->selectRaw('COALESCE(SUM(amount_usd_minor), 0) as total_usd_minor')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->all();

        $missingEvidence = $this->scopedQuery($project, $filters)
            ->where('attachments_count', 0)
            ->count();

        return [
            'item_count' => (int) ($totals->item_count ?? 0),
            'total_usd' => MoneyResource::fromMinor((int) ($totals->total_usd_minor ?? 0), 'USD', 2),
            'total_lak' => MoneyResource::fromMinor((int) ($totals->total_lak_minor ?? 0), 'LAK', 0),
            'missing_evidence_count' => $missingEvidence,
            'by_status' => array_map(
                fn (string $status): array => [
                    'status' => $status,
                    'count' => (int) ($byStatus[$status]->item_count ?? 0),
                    'total_usd' => MoneyResource::fromMinor(
                        (int) ($byStatus[$status]->total_usd_minor ?? 0), 'USD', 2
                    ),
                ],
                ['draft', 'submitted', 'approved', 'rejected'],
            ),
        ];
    }

    /**
     * ສະຫຼຸບຕາມແຫຼ່ງທຶນ — ຄຳຖາມຫຼັກຂອງລາຍຮັບຄື "ເງິນມາຈາກໃສແດ່".
     *
     * @return list<array<string, mixed>>
     */
    public function byPayer(Project $project, ReportFilters $filters): array
    {
        $rows = $this->scopedQuery($project, $filters)
            ->toBase()
            ->leftJoin('payers', 'revenues.payer_id', '=', 'payers.id')
            ->select(['payers.id', 'payers.name'])
            ->selectRaw('COUNT(revenues.id) as item_count')
            ->selectRaw('SUM(revenues.amount_usd_minor) as total_usd_minor')
            ->selectRaw('SUM(revenues.amount_lak_minor) as total_lak_minor')
            ->groupBy('payers.id', 'payers.name')
            ->orderByDesc('total_usd_minor')
            ->get();

        $grandTotal = (int) $rows->sum(fn (stdClass $row): int => (int) $row->total_usd_minor);
        $result = [];

        foreach ($rows as $row) {
            $usd = (int) $row->total_usd_minor;

            $result[] = [
                'payer_id' => $row->id === null ? null : (int) $row->id,
                // ແຖວທີ່ບໍ່ລະບຸແຫຼ່ງທຶນຕ້ອງປາກົດເປັນກຸ່ມຂອງມັນເອງ ບໍ່ແມ່ນເຊື່ອງ
                'label' => $row->name ?? 'ບໍ່ໄດ້ລະບຸແຫຼ່ງທຶນ',
                'count' => (int) $row->item_count,
                'usd' => MoneyResource::fromMinor($usd, 'USD', 2),
                'lak' => MoneyResource::fromMinor((int) $row->total_lak_minor, 'LAK', 0),
                // ຄິດ % ຢູ່ backend — ໃຫ້ໜ້າຈໍກັບ PDF ໄດ້ຕົວເລກດຽວກັນ
                'percentage' => $grandTotal > 0 ? round($usd / $grandTotal * 100, 2) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * ລາຍການລະອຽດ — ແຖວດຽວກັນທີ່ໜ້າຈໍສະແດງ ແລະ PDF ພິມ.
     *
     * ບໍ່ແບ່ງໜ້າໂດຍເຈດຕະນາ; ຜູ້ເອີ້ນຕ້ອງກວດ {@see self::rowCount()} ທຽບກັບ
     * `config('pdf.max_rows')` ກ່ອນ. ຮຽງເກົ່າ→ໃໝ່ ຄືໃບທີ່ພິມອອກມາ.
     *
     * @return EloquentCollection<int, Revenue>
     */
    public function detailedList(Project $project, ReportFilters $filters): EloquentCollection
    {
        return $this->scopedQuery($project, $filters)
            ->with(['payer:id,name'])
            ->orderBy('revenues.received_date')
            ->orderBy('revenues.id')
            ->get();
    }

    /** ຈຳນວນແຖວທີ່ຕົວກັ່ນຕອງນີ້ໃຫ້ — ນັບກ່ອນດຶງ ເພື່ອບໍ່ໃຫ້ດຶງແຖວເປັນແສນເຂົ້າ memory */
    public function rowCount(Project $project, ReportFilters $filters): int
    {
        return $this->scopedQuery($project, $filters)->count();
    }

    /** @return Builder<Revenue> */
    private function scopedQuery(Project $project, ReportFilters $filters, bool $withStatus = true): Builder
    {
        return Revenue::query()
            ->where('revenues.project_id', $project->id)
            ->when($withStatus, fn (Builder $q) => $q->whereIn('revenues.status', $filters->statuses))
            ->when($filters->dateFrom, fn (Builder $q, $d) => $q->where('revenues.received_date', '>=', $d))
            ->when($filters->dateTo, fn (Builder $q, $d) => $q->where('revenues.received_date', '<=', $d))
            ->when($filters->payerId, fn (Builder $q, $id) => $q->where('revenues.payer_id', $id))
            // ຄໍລຳດຽວກັນກັບທີ່ RevenueController ຄົ້ນຫາ — ຄຳດຽວກັນຕ້ອງໃຫ້ຜົນດຽວກັນ
            // ບໍ່ວ່າຈະພິມໃສ່ໜ້າລາຍຮັບ ຫຼື ໜ້າລາຍງານ
            ->when($filters->search, fn (Builder $q, string $term) => $q->where(
                fn (Builder $sub) => $sub->where('revenues.title', 'like', "%{$term}%")
                    ->orWhere('revenues.reference', 'like', "%{$term}%")
                    ->orWhere('revenues.doc_no', 'like', "%{$term}%")
            ));
    }
}

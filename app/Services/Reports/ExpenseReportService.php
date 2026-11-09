<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Http\Resources\MoneyResource;
use App\Models\Expense;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * ລາຍງານທັງໝົດອີງໃສ່ `amount_usd_minor` / `amount_lak_minor` ທີ່ຄິດໄວ້ລ່ວງໜ້າ.
 *
 * ນີ້ຄືເຫດຜົນທີ່ພາກ 2.1.ຄ ຍອມຜິດຫຼັກ normalization: ລາຍງານກາຍເປັນ `SUM()`
 * ທຳມະດາເໜືອ index — ບໍ່ຕ້ອງ JOIN exchange_rates ຫຼື ຄິດເລກຕໍ່ແຖວ.
 *
 * ໝາຍເຫດ: query ລວມໃຊ້ `->toBase()` — ແຖວລວມບໍ່ແມ່ນ Expense entity ຈຶ່ງບໍ່ຄວນ
 * hydrate ເປັນ model, ແຕ່ຍັງຕ້ອງຜ່ານ Eloquent ກ່ອນເພື່ອບໍ່ໃຫ້ເສຍ soft-delete scope.
 */
final class ExpenseReportService
{
    /**
     * ຍອດລວມ + ຈຳນວນລາຍການແຍກຕາມສະຖານະ.
     *
     * `by_status` ນັບທຸກສະຖານະສະເໝີ (ບໍ່ຂຶ້ນກັບ filter) ເພື່ອໃຫ້ໜ້າຈໍບອກໄດ້ວ່າ
     * "ຍັງມີ 12 ລາຍການລໍຖ້າອະນຸມັດ" — ບໍ່ດັ່ງນັ້ນຄົນຈະບໍ່ຮູ້ວ່າຍອດທີ່ເຫັນຂາດຫຍັງໄປ.
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
     * ສະຫຼຸບຕາມໝວດ — ໃຊ້ໃນ pie/bar chart.
     *
     * ໄວເພາະ index `idx_project_cat_date` ຮອງຮັບ WHERE + GROUP BY ໂດຍກົງ.
     *
     * @return list<array<string, mixed>>
     */
    public function byCategory(Project $project, ReportFilters $filters): array
    {
        $rows = $this->scopedQuery($project, $filters)
            ->toBase()
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select([
                'expense_categories.id',
                'expense_categories.name_lo',
                'expense_categories.color',
            ])
            ->selectRaw('COUNT(expenses.id) as item_count')
            ->selectRaw('SUM(expenses.amount_usd_minor) as total_usd_minor')
            ->selectRaw('SUM(expenses.amount_lak_minor) as total_lak_minor')
            ->groupBy('expense_categories.id', 'expense_categories.name_lo', 'expense_categories.color')
            ->orderByDesc('total_usd_minor')
            ->get();

        // 80 ແຖວ (36% ຂອງຍອດ) ໃນໄຟລ໌ເດີມບໍ່ມີໝວດ — ຕ້ອງສະແດງເປັນກຸ່ມ ບໍ່ແມ່ນເຊື່ອງ
        return $this->withPercentage($rows, fn (stdClass $row): array => [
            'category_id' => $row->id === null ? null : (int) $row->id,
            'label' => $row->name_lo ?? 'ບໍ່ໄດ້ລະບຸໝວດ',
            'color' => $row->color ?? '#94a3b8',
            'count' => (int) $row->item_count,
        ]);
    }

    /**
     * ສະຫຼຸບຕາມຜູ້ຈ່າຍ — sheet `Sheet1` ຂອງໄຟລ໌ເດີມ.
     *
     * @return list<array<string, mixed>>
     */
    public function byPayer(Project $project, ReportFilters $filters): array
    {
        $rows = $this->scopedQuery($project, $filters)
            ->toBase()
            ->leftJoin('payers', 'expenses.payer_id', '=', 'payers.id')
            ->select(['payers.id', 'payers.name'])
            ->selectRaw('COUNT(expenses.id) as item_count')
            ->selectRaw('SUM(expenses.amount_usd_minor) as total_usd_minor')
            ->selectRaw('SUM(expenses.amount_lak_minor) as total_lak_minor')
            ->groupBy('payers.id', 'payers.name')
            ->orderByDesc('total_usd_minor')
            ->get();

        return $this->withPercentage($rows, fn (stdClass $row): array => [
            'payer_id' => $row->id === null ? null : (int) $row->id,
            'label' => $row->name ?? 'ບໍ່ໄດ້ລະບຸຜູ້ຈ່າຍ',
            'count' => (int) $row->item_count,
        ]);
    }

    /**
     * ຍອດຕໍ່ເດືອນ + ຍອດສະສົມ.
     *
     * sheet `Report` ຂອງໄຟລ໌ເດີມເປັນ snapshot ທີ່ຄ້າງຢູ່ 2026-05-09 ຂະນະຂໍ້ມູນມີເຖິງ
     * 2026-08 — ອັນນີ້ຄິດສົດທຸກຄັ້ງທີ່ຮ້ອງ.
     *
     * @return list<array<string, mixed>>
     */
    public function monthlyTrend(Project $project, ReportFilters $filters): array
    {
        $month = $this->monthExpression();

        $rows = $this->scopedQuery($project, $filters)
            ->toBase()
            ->selectRaw("{$month} as month")
            ->selectRaw('COUNT(*) as item_count')
            ->selectRaw('SUM(amount_usd_minor) as total_usd_minor')
            ->selectRaw('SUM(amount_lak_minor) as total_lak_minor')
            ->groupByRaw($month)
            ->orderByRaw($month)
            ->get();

        $cumulativeUsd = 0;
        $result = [];

        foreach ($rows as $row) {
            $usd = (int) $row->total_usd_minor;
            $cumulativeUsd += $usd;

            $result[] = [
                'month' => (string) $row->month,
                'count' => (int) $row->item_count,
                'usd' => MoneyResource::fromMinor($usd, 'USD', 2),
                'lak' => MoneyResource::fromMinor((int) $row->total_lak_minor, 'LAK', 0),
                'cumulative_usd' => MoneyResource::fromMinor($cumulativeUsd, 'USD', 2),
            ];
        }

        return $result;
    }

    /**
     * ງົບປະມານ (sheet `ຕົ້ນທຶນ`) ທຽບກັບລາຍຈ່າຍຈິງ ຕໍ່ໝວດ.
     *
     * ທຽບໃນ USD ເພາະ BOQ ເປັນ USD ແຕ່ລາຍຈ່າຍຈິງມີທັງກີບ/ບາດ/ໂດລາ —
     * ເປັນໄປໄດ້ພຽງເພາະທັງສອງຕາຕະລາງເກັບ `amount_usd_minor` ໄວ້ລ່ວງໜ້າ.
     *
     * ໝວດທີ່ມີແຕ່ຂ້າງໃດຂ້າງໜຶ່ງກໍປາກົດ (ງົບທີ່ຍັງບໍ່ໄດ້ໃຊ້ / ຈ່າຍນອກງົບ) —
     * ນີ້ຄືສອງກໍລະນີທີ່ຜູ້ຄຸມໂຄງການຢາກເຫັນທີ່ສຸດ.
     *
     * @return array<string, mixed>
     */
    public function budgetVsActual(Project $project, ReportFilters $filters): array
    {
        /** @var array<int|string, stdClass> $budget */
        $budget = DB::table('budget_items')
            ->leftJoin('expense_categories', 'budget_items.category_id', '=', 'expense_categories.id')
            ->where('budget_items.project_id', $project->id)
            ->select(['expense_categories.id', 'expense_categories.name_lo', 'expense_categories.color'])
            ->selectRaw('SUM(budget_items.amount_usd_minor) as budget_usd_minor')
            ->groupBy('expense_categories.id', 'expense_categories.name_lo', 'expense_categories.color')
            ->get()
            ->keyBy(fn (stdClass $row): string => (string) ($row->id ?? ''))
            ->all();

        /** @var array<int|string, stdClass> $actual */
        $actual = $this->scopedQuery($project, $filters)
            ->toBase()
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select(['expense_categories.id', 'expense_categories.name_lo', 'expense_categories.color'])
            ->selectRaw('SUM(expenses.amount_usd_minor) as actual_usd_minor')
            ->groupBy('expense_categories.id', 'expense_categories.name_lo', 'expense_categories.color')
            ->get()
            ->keyBy(fn (stdClass $row): string => (string) ($row->id ?? ''))
            ->all();

        $rows = [];
        $totalBudget = 0;
        $totalActual = 0;

        foreach (array_keys($budget + $actual) as $key) {
            $meta = $budget[$key] ?? $actual[$key];
            $budgetUsd = (int) ($budget[$key]->budget_usd_minor ?? 0);
            $actualUsd = (int) ($actual[$key]->actual_usd_minor ?? 0);

            $totalBudget += $budgetUsd;
            $totalActual += $actualUsd;

            $rows[] = [
                'category_id' => $meta->id === null ? null : (int) $meta->id,
                'label' => $meta->name_lo ?? 'ບໍ່ໄດ້ລະບຸໝວດ',
                'color' => $meta->color ?? '#94a3b8',
                'budget_usd' => MoneyResource::fromMinor($budgetUsd, 'USD', 2),
                'actual_usd' => MoneyResource::fromMinor($actualUsd, 'USD', 2),
                // ບວກ = ຍັງເຫຼືອງົບ, ລົບ = ໃຊ້ເກີນ
                'variance_usd' => MoneyResource::fromMinor($budgetUsd - $actualUsd, 'USD', 2),
                'used_percentage' => $budgetUsd > 0 ? round($actualUsd / $budgetUsd * 100, 2) : null,
                'is_over_budget' => $budgetUsd > 0 && $actualUsd > $budgetUsd,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['actual_usd']['minor'] <=> $a['actual_usd']['minor']);

        return [
            'total_budget_usd' => MoneyResource::fromMinor($totalBudget, 'USD', 2),
            'total_actual_usd' => MoneyResource::fromMinor($totalActual, 'USD', 2),
            'total_variance_usd' => MoneyResource::fromMinor($totalBudget - $totalActual, 'USD', 2),
            'used_percentage' => $totalBudget > 0 ? round($totalActual / $totalBudget * 100, 2) : null,
            'by_category' => $rows,
        ];
    }

    /**
     * ລາຍການທີ່ຍັງບໍ່ມີຫຼັກຖານແນບ.
     *
     * ນີ້ຄື query ທີ່ Excel ເຮັດບໍ່ໄດ້ເລີຍ ແລະ ເປັນຄຸນຄ່າຫຼັກຂອງລະບົບໃໝ່:
     * ໃນໄຟລ໌ເດີມ ຮູບ 65 ຮູບບໍ່ໄດ້ຜູກກັບລາຍການໃດເລີຍ.
     *
     * @return LengthAwarePaginator<int, Expense>
     */
    public function missingEvidence(Project $project, ReportFilters $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->scopedQuery($project, $filters)
            ->where('attachments_count', 0)
            ->orderByDesc('amount_usd_minor')   // ຍອດໃຫຍ່ຂຶ້ນກ່ອນ — ຄວາມສ່ຽງສູງສຸດ
            ->with(['category:id,name_lo,color', 'payer:id,name', 'payee:id,name'])
            ->paginate($perPage);
    }

    /**
     * ລາຍການລະອຽດ — ແຖວດຽວກັນທີ່ໜ້າຈໍສະແດງ ແລະ PDF ພິມ.
     *
     * ບໍ່ແບ່ງໜ້າໂດຍເຈດຕະນາ: ລາຍງານທີ່ພິມອອກມາຕ້ອງກົງກັບທີ່ເຫັນຢູ່ໜ້າຈໍທຸກແຖວ.
     * ຜູ້ເອີ້ນຕ້ອງກວດ {@see self::rowCount()} ທຽບກັບ `config('pdf.max_rows')` ກ່ອນ.
     *
     * ຮຽງເກົ່າ→ໃໝ່ (ກົງກັນຂ້າມກັບໜ້າລາຍຈ່າຍ) ເພາະລາຍງານທີ່ພິມອອກມາອ່ານຕາມ
     * ລຳດັບເວລາ ແລະ ຍອດສະສົມທ້າຍໜ້າຈຶ່ງມີຄວາມໝາຍ.
     *
     * @return EloquentCollection<int, Expense>
     */
    public function detailedList(Project $project, ReportFilters $filters): EloquentCollection
    {
        return $this->scopedQuery($project, $filters)
            // ບໍ່ຕ້ອງໂຫຼດ currency: MoneyMinor cast ດຶງ decimal_places ຈາກ
            // Currency::decimalsFor() ທີ່ cache ໄວ້ຕະຫຼອດ ບໍ່ຕີ DB ຕໍ່ແຖວ
            ->with(['category:id,name_lo,color', 'payer:id,name', 'payee:id,name'])
            ->orderBy('expenses.expense_date')
            ->orderBy('expenses.id')
            ->get();
    }

    /** ຈຳນວນແຖວທີ່ຕົວກັ່ນຕອງນີ້ໃຫ້ — ນັບກ່ອນດຶງ ເພື່ອບໍ່ໃຫ້ດຶງແຖວເປັນແສນເຂົ້າ memory */
    public function rowCount(Project $project, ReportFilters $filters): int
    {
        return $this->scopedQuery($project, $filters)->count();
    }

    /**
     * ຄິດ % ຢູ່ backend ບໍ່ແມ່ນ frontend — ໃຫ້ chart ໃນ Vue ກັບ PDF ໄດ້ຕົວເລກດຽວກັນ.
     *
     * @param  Collection<int, stdClass>  $rows
     * @param  callable(stdClass): array<string, mixed>  $label
     * @return list<array<string, mixed>>
     */
    private function withPercentage(Collection $rows, callable $label): array
    {
        $grandTotal = (int) $rows->sum(fn (stdClass $row): int => (int) $row->total_usd_minor);
        $result = [];

        foreach ($rows as $row) {
            $usd = (int) $row->total_usd_minor;

            $result[] = [
                ...$label($row),
                'usd' => MoneyResource::fromMinor($usd, 'USD', 2),
                'lak' => MoneyResource::fromMinor((int) $row->total_lak_minor, 'LAK', 0),
                'percentage' => $grandTotal > 0 ? round($usd / $grandTotal * 100, 2) : 0.0,
            ];
        }

        return $result;
    }

    /** @return Builder<Expense> */
    private function scopedQuery(Project $project, ReportFilters $filters, bool $withStatus = true): Builder
    {
        return Expense::query()
            ->where('expenses.project_id', $project->id)
            ->when($withStatus, fn (Builder $q) => $q->whereIn('expenses.status', $filters->statuses))
            ->when($filters->dateFrom, fn (Builder $q, $d) => $q->where('expenses.expense_date', '>=', $d))
            ->when($filters->dateTo, fn (Builder $q, $d) => $q->where('expenses.expense_date', '<=', $d))
            ->when($filters->categoryId, fn (Builder $q, $id) => $q->where('expenses.category_id', $id))
            ->when($filters->payerId, fn (Builder $q, $id) => $q->where('expenses.payer_id', $id))
            // LIKE ບໍ່ໃຊ້ index ແຕ່ພຽງພໍທີ່ຂະໜາດນີ້ — ພາກ 2.1.ງ ທາງເລືອກ #3.
            // ຄໍລຳດຽວກັນກັບທີ່ໜ້າລາຍຈ່າຍຄົ້ນຫາ ເພື່ອໃຫ້ຄຳດຽວກັນໄດ້ຜົນດຽວກັນທັງສອງໜ້າ.
            ->when($filters->search, fn (Builder $q, string $term) => $q->where(
                fn (Builder $sub) => $sub->where('expenses.title', 'like', "%{$term}%")
                    ->orWhere('expenses.description', 'like', "%{$term}%")
                    ->orWhere('expenses.doc_no', 'like', "%{$term}%")
            ));
    }

    /**
     * ດຶງ "YYYY-MM" ອອກຈາກວັນທີ.
     *
     * ຂຽນຕໍ່ driver ໂດຍເຈດຕະນາ: dev ແລ່ນເທິງ SQLite ແຕ່ production ຄື MySQL 8 —
     * `DATE_FORMAT` ບໍ່ມີໃນ SQLite ແລະ `strftime` ບໍ່ມີໃນ MySQL.
     *
     * @return literal-string
     */
    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', expenses.expense_date)",
            'pgsql' => "to_char(expenses.expense_date, 'YYYY-MM')",
            default => "DATE_FORMAT(expenses.expense_date, '%Y-%m')",
        };
    }
}

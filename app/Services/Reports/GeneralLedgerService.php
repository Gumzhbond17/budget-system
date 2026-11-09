<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Http\Resources\MoneyResource;
use App\Models\Currency;
use App\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * ບັນຊີແຍກປະເພດ (General Ledger) — ລວມລາຍຮັບ ແລະ ລາຍຈ່າຍເປັນລຳດັບເວລາດຽວ
 * ພ້ອມຍອດຄົງເຫຼືອແລ່ນ (running balance).
 *
 * ## ເປັນຫຍັງຄິດຍອດເປັນ USD ແລະ ກີບ minor
 * ແຕ່ລະແຖວອາດເປັນ ກີບ/ບາດ/ໂດລາ — ບວກກັນຊື່ໆບໍ່ໄດ້. ຄໍລຳ `amount_usd_minor`
 * ແລະ `amount_lak_minor` ຖືກຄິດໄວ້ລ່ວງໜ້າຕອນບັນທຶກ (denormalized ໂດຍເຈດຕະນາ,
 * ພາກ 2.1.ຄ) ດ້ວຍ rate ທີ່ snapshot ໄວ້ — ຈຶ່ງບວກເປັນ integer ໄດ້ໂດຍບໍ່ມີ
 * rounding ແລະ ຍອດຍ້ອນຫຼັງບໍ່ປ່ຽນເມື່ອມີການແກ້ rate ພາຍຫຼັງ.
 *
 * ຄືນທັງສອງສະກຸນ: ໜ້າຈໍສະແດງເປັນກີບ (ສະກຸນທີ່ຜູ້ໃຊ້ຄິດເປັນ) ແຕ່ USD ຍັງເປັນ
 * ສະກຸນທຽບສຳລັບງົບປະມານ ແລະ ລາຍງານ. ບໍ່ແປງຢູ່ນີ້ — ອ່ານຈາກ snapshot ທັງຄູ່.
 *
 * ## ເປັນຫຍັງໃຊ້ UNION ບໍ່ແມ່ນສອງ query ແລ້ວ merge ໃນ PHP
 * ຍອດຄົງເຫຼືອແລ່ນຕ້ອງການລຳດັບເວລາທີ່ແນ່ນອນຂ້າມສອງຕາຕະລາງ. ດຶງແຍກກັນແລ້ວ
 * ຮຽງໃນ PHP ຈະໃຊ້ບໍ່ໄດ້ທັນທີທີ່ມີ pagination — ໜ້າ 2 ຈະບໍ່ຮູ້ຍອດຍົກມາ.
 */
final class GeneralLedgerService
{
    /**
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     * }
     */
    public function forProject(Project $project, ReportFilters $filters): array
    {
        $rows = $this->unionQuery($project, $filters)
            ->orderBy('entry_date')
            ->orderBy('doc_no')
            ->orderBy('id')
            ->get();

        $revenueTotal = 0;
        $expenseTotal = 0;
        $revenueLakTotal = 0;
        $expenseLakTotal = 0;
        $entries = [];

        foreach ($rows as $row) {
            $isRevenue = $row->entry_type === 'revenue';
            $usdMinor = (int) $row->amount_usd_minor;
            $lakMinor = (int) $row->amount_lak_minor;

            if ($isRevenue) {
                $revenueTotal += $usdMinor;
                $revenueLakTotal += $lakMinor;
            } else {
                $expenseTotal += $usdMinor;
                $expenseLakTotal += $lakMinor;
            }

            $entries[] = [
                'uuid' => $row->uuid,
                'doc_no' => $row->doc_no,
                'date' => $row->entry_date,
                'title' => $row->title,
                'type' => $row->entry_type,           // 'revenue' | 'expense' — Vue ໃຊ້ເລືອກສີ
                'status' => $row->status,
                'counterparty' => $row->counterparty,  // ຜູ້ຈ່າຍ (ລາຍຮັບ) / ຜູ້ຮັບເງິນ (ລາຍຈ່າຍ)

                // ຈຳນວນເດີມໃນສະກຸນທີ່ບັນທຶກ — ນັກບັນຊີຢາກເຫັນຕົວເລກທີ່ຕົນປ້ອນ
                'amount' => MoneyResource::fromMinor(
                    (int) $row->amount_minor,
                    (string) $row->currency_code,
                    Currency::decimalsFor((string) $row->currency_code),
                ),
                'amount_usd' => MoneyResource::fromMinor($usdMinor, 'USD', 2),
                'amount_lak' => MoneyResource::fromMinor($lakMinor, 'LAK', 0),

                // ຍອດຄົງເຫຼືອຫຼັງແຖວນີ້ — ຄິດຢູ່ server ບ່ອນດຽວ ບໍ່ໃຫ້ Vue ສະສົມເອງ
                'balance_usd' => MoneyResource::fromMinor($revenueTotal - $expenseTotal, 'USD', 2),
                'balance_lak' => MoneyResource::fromMinor($revenueLakTotal - $expenseLakTotal, 'LAK', 0),
            ];
        }

        return [
            'entries' => $entries,
            'totals' => $this->totals(
                $project,
                $revenueTotal,
                $expenseTotal,
                $revenueLakTotal,
                $expenseLakTotal,
                count($entries),
            ),
        ];
    }

    /**
     * ສະຫຼຸບຕໍ່ເດືອນ: ເງິນເຂົ້າ / ເງິນອອກ / ສຸດທິ ພ້ອມຍອດຄົງເຫຼືອສະສົມ.
     *
     * ນີ້ຄື "ສ່ວນສະຫຼຸບ" ຂອງລາຍງານ — ຕອບຄຳຖາມ "ເດືອນໃດເງິນຂາດ" ໂດຍບໍ່ຕ້ອງ
     * ກວາດຕາອ່ານແຖວລະອຽດເປັນຮ້ອຍແຖວ. ຄິດຈາກແຖວຊຸດດຽວກັນກັບສ່ວນລະອຽດ
     * ຈຶ່ງບໍ່ມີທາງທີ່ສອງສ່ວນຈະບໍ່ກົງກັນ.
     *
     * @param  list<array<string, mixed>>  $entries  ຜົນຈາກ {@see self::forProject()}
     * @return list<array<string, mixed>>
     */
    public function monthlySummary(array $entries): array
    {
        /** @var array<string, array{revenue_usd: int, expense_usd: int, revenue_lak: int, expense_lak: int, count: int}> $months */
        $months = [];

        foreach ($entries as $entry) {
            // ວັນທີເປັນ "YYYY-MM-DD" ສະເໝີ (DATE column) — ຕັດເອົາ 7 ຕົວທຳອິດ
            // ໄວກວ່າ ແລະ ບໍ່ຂຶ້ນກັບ driver ຄືກັບ DATE_FORMAT/strftime
            $month = substr((string) $entry['date'], 0, 7);

            $months[$month] ??= [
                'revenue_usd' => 0, 'expense_usd' => 0,
                'revenue_lak' => 0, 'expense_lak' => 0,
                'count' => 0,
            ];

            $bucket = $entry['type'] === 'revenue' ? 'revenue' : 'expense';

            /** @var array{minor: int} $usd */
            $usd = $entry['amount_usd'];
            /** @var array{minor: int} $lak */
            $lak = $entry['amount_lak'];

            $months[$month]["{$bucket}_usd"] += $usd['minor'];
            $months[$month]["{$bucket}_lak"] += $lak['minor'];
            $months[$month]['count']++;
        }

        ksort($months);

        $cumulativeUsd = 0;
        $cumulativeLak = 0;
        $result = [];

        foreach ($months as $month => $sums) {
            $netUsd = $sums['revenue_usd'] - $sums['expense_usd'];
            $netLak = $sums['revenue_lak'] - $sums['expense_lak'];
            $cumulativeUsd += $netUsd;
            $cumulativeLak += $netLak;

            $result[] = [
                'month' => $month,
                'count' => $sums['count'],
                'revenue_usd' => MoneyResource::fromMinor($sums['revenue_usd'], 'USD', 2),
                'expense_usd' => MoneyResource::fromMinor($sums['expense_usd'], 'USD', 2),
                'net_usd' => MoneyResource::fromMinor($netUsd, 'USD', 2),
                'revenue_lak' => MoneyResource::fromMinor($sums['revenue_lak'], 'LAK', 0),
                'expense_lak' => MoneyResource::fromMinor($sums['expense_lak'], 'LAK', 0),
                'net_lak' => MoneyResource::fromMinor($netLak, 'LAK', 0),
                // ຍອດຄົງເຫຼືອທ້າຍເດືອນ — ຕ້ອງກົງກັບ balance ຂອງແຖວສຸດທ້າຍຂອງເດືອນນັ້ນ
                'balance_usd' => MoneyResource::fromMinor($cumulativeUsd, 'USD', 2),
                'balance_lak' => MoneyResource::fromMinor($cumulativeLak, 'LAK', 0),
            ];
        }

        return $result;
    }

    /** ຈຳນວນແຖວທີ່ຕົວກັ່ນຕອງນີ້ໃຫ້ — ນັບກ່ອນດຶງ ເພື່ອບໍ່ໃຫ້ດຶງແຖວເປັນແສນເຂົ້າ memory */
    public function rowCount(Project $project, ReportFilters $filters): int
    {
        return $this->unionQuery($project, $filters)->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function totals(
        Project $project,
        int $revenueTotal,
        int $expenseTotal,
        int $revenueLakTotal,
        int $expenseLakTotal,
        int $count,
    ): array {
        $budgetUsd = $this->budgetInMinor($project, 'USD');
        $budgetLak = $this->budgetInMinor($project, 'LAK');

        return [
            'entry_count' => $count,
            'revenue_usd' => MoneyResource::fromMinor($revenueTotal, 'USD', 2),
            'expense_usd' => MoneyResource::fromMinor($expenseTotal, 'USD', 2),
            'revenue_lak' => MoneyResource::fromMinor($revenueLakTotal, 'LAK', 0),
            'expense_lak' => MoneyResource::fromMinor($expenseLakTotal, 'LAK', 0),

            // ຍອດຄົງເຫຼືອ = ເງິນເຂົ້າ − ເງິນອອກ. ຕິດລົບ = ຈ່າຍເກີນເງິນທີ່ຮັບມາ.
            'balance_usd' => MoneyResource::fromMinor($revenueTotal - $expenseTotal, 'USD', 2),
            'balance_lak' => MoneyResource::fromMinor($revenueLakTotal - $expenseLakTotal, 'LAK', 0),

            // ງົບປະມານທີ່ຍັງເຫຼືອ = ງົບ − ລາຍຈ່າຍຈິງ. ຄົນລະຄຳຖາມກັບ balance:
            // balance ຖາມ "ເງິນສົດເຫຼືອເທົ່າໃດ", ນີ້ຖາມ "ຍັງໃຊ້ໄດ້ອີກເທົ່າໃດ".
            // null = ຍັງບໍ່ໄດ້ຕັ້ງງົບ ຫຼື ຕັ້ງໄວ້ເປັນສະກຸນອື່ນ (ເບິ່ງ budgetInMinor)
            'budget_usd' => $budgetUsd === null
                ? null
                : MoneyResource::fromMinor($budgetUsd, 'USD', 2),
            'budget_remaining_usd' => $budgetUsd === null
                ? null
                : MoneyResource::fromMinor($budgetUsd - $expenseTotal, 'USD', 2),
            'budget_lak' => $budgetLak === null
                ? null
                : MoneyResource::fromMinor($budgetLak, 'LAK', 0),
            'budget_remaining_lak' => $budgetLak === null
                ? null
                : MoneyResource::fromMinor($budgetLak - $expenseLakTotal, 'LAK', 0),

            // ໜ້າຈໍໃຊ້ແຍກ "ຍັງບໍ່ໄດ້ຕັ້ງງົບ" ອອກຈາກ "ຕັ້ງໄວ້ເປັນສະກຸນທີ່ສະແດງບໍ່ໄດ້"
            // — ສອງອັນນີ້ບອກຜູ້ໃຊ້ຄົນລະເລື່ອງ ແຕ່ຄືນ null ຄືກັນທັງຄູ່
            'budget_currency' => $project->budget_amount_minor > 0
                ? $project->budget_currency_code
                : null,
        ];
    }

    /**
     * ງົບປະມານໂຄງການເປັນ minor ຂອງສະກຸນທີ່ຂໍ.
     *
     * ງົບເກັບໃນສະກຸນທີ່ຕັ້ງໄວ້. ຖ້າຕັ້ງເປັນສະກຸນອື່ນຕ້ອງແປງ — ແຕ່ງົບບໍ່ມີ rate
     * snapshot ຂອງຕົນເອງຄືລາຍຮັບ/ລາຍຈ່າຍ ຈຶ່ງບໍ່ມີຄຳຕອບທີ່ audit-safe.
     * ດັ່ງນັ້ນຄືນ null ແທນທີ່ຈະເດົາ ແລ້ວໜ້າຈໍຈະບອກເຫດຜົນແທນຕົວເລກ.
     */
    private function budgetInMinor(Project $project, string $currency): ?int
    {
        if ($project->budget_amount_minor <= 0) {
            return null;
        }

        return $project->budget_currency_code === $currency
            ? $project->budget_amount_minor
            : null;
    }

    /**
     * UNION ALL ຂອງສອງຕາຕະລາງ ໃຫ້ໄດ້ຮູບຮ່າງຄໍລຳດຽວກັນ.
     *
     * ໃຊ້ query builder ດິບ (ບໍ່ແມ່ນ Eloquent) — UNION ຂ້າມສອງ model
     * ບໍ່ໄດ້ໃຫ້ປະໂຫຍດຫຍັງຈາກ Eloquent ນອກຈາກ overhead ຂອງການ hydrate.
     */
    private function unionQuery(Project $project, ReportFilters $filters): Builder
    {
        $revenues = DB::table('revenues')
            ->selectRaw("
                revenues.id,
                revenues.uuid,
                revenues.doc_no,
                revenues.received_date as entry_date,
                revenues.title,
                'revenue' as entry_type,
                revenues.status,
                payers.name as counterparty,
                revenues.currency_code,
                revenues.amount_minor,
                revenues.amount_usd_minor,
                revenues.amount_lak_minor
            ")
            ->leftJoin('payers', 'payers.id', '=', 'revenues.payer_id')
            ->where('revenues.project_id', $project->id)
            ->whereNull('revenues.deleted_at')
            ->whereIn('revenues.status', $filters->statuses)
            ->when($filters->dateFrom, fn ($q) => $q->where('revenues.received_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->where('revenues.received_date', '<=', $filters->dateTo))
            // payer_id ມີຢູ່ທັງສອງຕາຕະລາງ ແລະ ໝາຍເຖິງ "ໃຜອອກເງິນ" ຄືກັນ —
            // ຈຶ່ງກັ່ນຕອງດ້ວຍຄຳຖາມດຽວກັນໄດ້ ເຖິງແມ່ນຄໍລຳ counterparty ທີ່ສະແດງ
            // ຈະເປັນຜູ້ຮັບເງິນຢູ່ຂ້າງລາຍຈ່າຍ
            ->when($filters->payerId, fn ($q) => $q->where('revenues.payer_id', $filters->payerId))
            ->when($filters->search, fn ($q) => $q->where(
                fn ($sub) => $sub->where('revenues.title', 'like', "%{$filters->search}%")
                    ->orWhere('revenues.reference', 'like', "%{$filters->search}%")
                    ->orWhere('revenues.doc_no', 'like', "%{$filters->search}%")
            ));

        $expenses = DB::table('expenses')
            ->selectRaw("
                expenses.id,
                expenses.uuid,
                expenses.doc_no,
                expenses.expense_date as entry_date,
                expenses.title,
                'expense' as entry_type,
                expenses.status,
                payees.name as counterparty,
                expenses.currency_code,
                expenses.amount_minor,
                expenses.amount_usd_minor,
                expenses.amount_lak_minor
            ")
            ->leftJoin('payees', 'payees.id', '=', 'expenses.payee_id')
            ->where('expenses.project_id', $project->id)
            ->whereNull('expenses.deleted_at')
            ->whereIn('expenses.status', $filters->statuses)
            ->when($filters->dateFrom, fn ($q) => $q->where('expenses.expense_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo, fn ($q) => $q->where('expenses.expense_date', '<=', $filters->dateTo))
            ->when($filters->payerId, fn ($q) => $q->where('expenses.payer_id', $filters->payerId))
            ->when($filters->search, fn ($q) => $q->where(
                fn ($sub) => $sub->where('expenses.title', 'like', "%{$filters->search}%")
                    ->orWhere('expenses.description', 'like', "%{$filters->search}%")
                    ->orWhere('expenses.doc_no', 'like', "%{$filters->search}%")
            ));

        // unionAll ບໍ່ແມ່ນ union — union ຈະຕັດແຖວທີ່ຄ່າຊ້ຳກັນທຸກຄໍລຳຖິ້ມ,
        // ເຊິ່ງເປັນໄປໄດ້ຢ່າງສົມເຫດສົມຜົນ (ຈ່າຍຄ່າອາຫານ 50,000 ກີບ ສອງເທື່ອໃນມື້ດຽວ)
        return $revenues->unionAll($expenses);
    }
}

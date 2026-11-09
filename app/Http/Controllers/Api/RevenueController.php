<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Money\Money;
use App\Http\Controllers\Controller;
use App\Http\Resources\RevenueResource;
use App\Models\Currency;
use App\Models\Project;
use App\Models\Revenue;
use App\Services\RevenueService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * ລາຍຮັບ — ເງິນເຂົ້າໂຄງການ.
 *
 * ຍອດແປງທັງໝົດຄິດຢູ່ `RevenueService` ບ່ອນດຽວ ບໍ່ແມ່ນຢູ່ນີ້ (CLAUDE.md).
 */
final class RevenueController extends Controller
{
    /** ຕ້ອງກົງກັບຕົວເລືອກສູງສຸດຂອງການແບ່ງໜ້າຢູ່ໜ້າຈໍ (20/50/100/150) */
    private const MAX_PER_PAGE = 150;

    /**
     * ເພດານຂອງ `?all=1` — ໜ້າຈໍໂຫຼດທັງໂຄງການມາໃຫ້ຕາຕະລາງກັ່ນຕອງ, ຮຽງ ແລະ
     * ແບ່ງໜ້າເອງຢູ່ browser (client-side row model ຂອງ AG Grid).
     *
     * ຍັງມີເພດານຢູ່ ບໍ່ແມ່ນດຶງບໍ່ຈຳກັດ: ເຫດຜົນເກົ່າຂອງ MAX_PER_PAGE ຍັງໃຊ້ໄດ້ —
     * ການສ້າງ Resource ພ້ອມກັນທັງຕາຕະລາງກິນໜ່ວຍຄວາມຈຳ. ໂຄງການຈິງມີລາຍການ
     * ຫຼັກຮ້ອຍ ເພດານນີ້ຈຶ່ງເຫຼືອບ່ອນຫຼາຍ ແລະ `meta.total` ບອກໜ້າຈໍໄດ້ວ່າ
     * ຖືກຕັດບໍ ຖ້າມີໂຄງການໃດໃຫຍ່ເກີນຄາດ.
     */
    private const ALL_ROWS_CAP = 5000;

    /**
     * ຖັນທີ່ກົດຫົວຕາຕະລາງເພື່ອຮຽງໄດ້ → ຄໍລຳຈິງໃນຖານຂໍ້ມູນ.
     *
     * ເປັນ whitelist ບໍ່ແມ່ນຮັບຊື່ຄໍລຳຈາກ client ໂດຍກົງ — `orderBy()` ບໍ່ໄດ້
     * bind ຊື່ຄໍລຳເປັນພາລາມິເຕີ ການສົ່ງຕໍ່ຄ່າດິບຈຶ່ງເປັນຮູຮົ່ວ SQL injection.
     *
     * `amount` ຮຽງດ້ວຍ `amount_usd_minor` ບໍ່ແມ່ນ `amount_minor` ດ້ວຍເຫດຜົນ
     * ດຽວກັນກັບຕົວກັ່ນຕອງ: minor unit ຂອງແຕ່ລະສະກຸນປຽບທຽບກັນບໍ່ໄດ້ —
     * 200,000 ກີບ ຈະຖືກຖືວ່າໃຫຍ່ກວ່າ USD 1,000 (100,000 cents)
     */
    private const SORTABLE = [
        'date' => 'received_date',
        'doc_no' => 'doc_no',
        'title' => 'title',
        'amount' => 'amount_usd_minor',
        'evidence' => 'attachments_count',
    ];

    /** ສະຖານະທີ່ຮູ້ຈັກ — ຄ່າທີ່ຮັບໄດ້ຂອງຕົວກັ່ນຕອງຖັນສະຖານະ */
    private const STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    /**
     * ສະຖານະຮຽງຕາມ **ຂັ້ນຕອນ** ບໍ່ແມ່ນຕາມຕົວອັກສອນ:
     * ຮ່າງ → ສົ່ງແລ້ວ → ອະນຸມັດແລ້ວ → ປະຕິເສດ.
     *
     * ຂຽນ CASE ໄວ້ເອງ ບໍ່ແມ່ນປ່ອຍໃຫ້ `ORDER BY status` ຕັດສິນ ເພາະສອງ engine
     * ໃຫ້ຜົນຄົນລະຢ່າງ: MySQL ຮຽງ ENUM ຕາມລຳດັບທີ່ປະກາດ (ໄດ້ຂັ້ນຕອນພໍດີ) ແຕ່
     * SQLite ທີ່ test ໃຊ້ເກັບເປັນຂໍ້ຄວາມແລ້ວຮຽງຕາມຕົວອັກສອນ (approved, draft,
     * rejected, submitted) — ຂຽນໄວ້ຢູ່ນີ້ຈຶ່ງໄດ້ຜົນດຽວກັນທັງສອງບ່ອນ.
     *
     * ຕ້ອງເປັນຂໍ້ຄວາມຄົງທີ່ ບໍ່ແມ່ນປະກອບຈາກ `self::STATUSES` ດ້ວຍ loop:
     * `orderByRaw()` ຮັບແຕ່ literal-string ເພື່ອບໍ່ໃຫ້ຄ່າຈາກນອກໄຫຼເຂົ້າ SQL.
     */
    private const STATUS_SORT_CASE = "CASE status WHEN 'draft' THEN 0 WHEN 'submitted' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END";

    public function __construct(private RevenueService $revenues) {}

    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Revenue::class, $project]);

        // ຊ່ອງຂອບເຂດຈຳນວນເງິນຢູ່ຫົວຕາຕະລາງຮັບຄ່າທີ່ຈັດກຸ່ມຫຼັກພັນແລ້ວ ("1,000")
        // ຄືກັນກັບຊ່ອງໃນຟອມ — ລ້າງກ່ອນ validate ດ້ວຍເຫດຜົນດຽວກັນ
        $this->stripGrouping($request, 'amount_min', 'amount_max');

        // ໜຶ່ງຖັນຢູ່ຫົວຕາຕະລາງ = ໜຶ່ງຊຸດພາລາມິເຕີ (ເບິ່ງ ColumnFilter.vue).
        // ກັ່ນຕອງຢູ່ server ບໍ່ແມ່ນຢູ່ browser: ໜ້າຈໍເຫັນເທື່ອລະໜ້າ ການກັ່ນ
        // ສະເພາະແຖວທີ່ໂຫຼດມາແລ້ວຈະໃຫ້ຄຳຕອບຜິດທັນທີທີ່ມີຫຼາຍກວ່າໜຶ່ງໜ້າ
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'doc_no' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            'amount_min' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'amount_max' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'has_evidence' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'search' => ['nullable', 'string', 'max:120'],

            // 'status' ບໍ່ຢູ່ໃນ SORTABLE ເພາະຮຽງດ້ວຍ CASE ບໍ່ແມ່ນຄໍລຳກົງໆ
            'sort' => ['nullable', Rule::in([...array_keys(self::SORTABLE), 'status'])],
            'direction' => ['nullable', 'in:asc,desc'],

            'all' => ['nullable', 'boolean'],
        ], [
            'amount_min.regex' => 'ຈຳນວນເງິນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 1000 ຫຼື 1,000.50',
            'amount_max.regex' => 'ຈຳນວນເງິນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 1000 ຫຼື 1,000.50',
        ]);

        $revenues = $project->revenues()
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->where('received_date', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->where('received_date', '<=', $d))
            ->when($filters['doc_no'] ?? null, fn ($q, $term) => $q->where('doc_no', 'like', '%'.trim($term).'%'))

            // ຖັນ "ລາຍລະອຽດ" ຢູ່ໜ້າຈໍສະແດງຊື່ລາຍການ **ແລະ** ແຫຼ່ງທຶນ ຈຶ່ງກັ່ນທັງສອງ —
            // ກັ່ນສະເພາະ `title` ຈະເຮັດໃຫ້ພິມຊື່ຜູ້ໂອນທີ່ເຫັນຢູ່ຕໍ່ຕາແລ້ວບໍ່ໄດ້ຫຍັງເລີຍ
            ->when($filters['title'] ?? null, function ($q, $term) {
                $like = '%'.trim($term).'%';

                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhereHas('payer', fn ($p) => $p->where('name', 'like', $like)));
            })

            // ຂອບເຂດປຽບທຽບເປັນ USD — ມິຕິດຽວທີ່ທຸກແຖວມີຮ່ວມກັນ ບໍ່ວ່າຈະບັນທຶກ
            // ເປັນ ກີບ, ບາດ ຫຼື ໂດລາ (ຖັນ `amount_minor` ຄົນລະສະກຸນ ປຽບທຽບກັນບໍ່ໄດ້)
            ->when($filters['amount_min'] ?? null, fn ($q, $min) => $q->where('amount_usd_minor', '>=', $this->usdMinor($min)))
            ->when($filters['amount_max'] ?? null, fn ($q, $max) => $q->where('amount_usd_minor', '<=', $this->usdMinor($max)))

            // `filled` ບໍ່ແມ່ນ `isset`: "ບໍ່ມີຫຼັກຖານ" ສົ່ງມາເປັນ 0 ເຊິ່ງເປັນຄ່າທີ່
            // ຖືກຕ້ອງ ບໍ່ແມ່ນ "ບໍ່ໄດ້ກັ່ນຕອງ"
            ->when(
                $request->filled('has_evidence'),
                fn ($q) => $q->where('attachments_count', $request->boolean('has_evidence') ? '>' : '=', 0)
            )
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = '%'.trim($term).'%';

                // ຄໍລຳດຽວກັນກັບ RevenueReportService — ຄຳດຽວກັນຕ້ອງໃຫ້ຜົນດຽວກັນ
                // ບໍ່ວ່າຈະພິມໃສ່ໜ້ານີ້ ຫຼື ໜ້າລາຍງານ
                $q->where(fn ($w) => $w->where('title', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('doc_no', 'like', $like));
            })
            ->with(['payer', 'bank'])

            // ກົດຫົວຕາຕະລາງ = ຮຽງຕາມຖັນນັ້ນ; ບໍ່ໄດ້ກົດ = ລ່າສຸດທີ່ **ບັນທຶກ**
            // ຂຶ້ນກ່ອນ ບໍ່ແມ່ນລ່າສຸດຕາມວັນທີຮັບເງິນ — ບັນທຶກຍ້ອນຫຼັງແລ້ວຕ້ອງ
            // ເຫັນແຖວທີ່ຫາກໍໃສ່ຢູ່ເທິງສຸດ (ເຫດຜົນດຽວກັນກັບ ExpenseController::index)
            ->when(
                $filters['sort'] ?? null,
                fn ($q, string $sort) => $this->applySort($q, $sort, $filters['direction'] ?? 'asc'),
                fn ($q) => $q->orderByDesc('created_at'),
            )
            // ຕົວຕັດສິນສຸດທ້າຍສະເໝີ: ຫຼາຍແຖວມີຄ່າເທົ່າກັນໄດ້ (ສະຖານະ, ວັນທີ,
            // ຈຳນວນຫຼັກຖານ) ແລະ ຖ້າລຳດັບບໍ່ແນ່ນອນ ແຖວດຽວກັນຈະປາກົດສອງໜ້າ
            // ຫຼື ຫາຍໄປລະຫວ່າງໜ້າ
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            // ລິ້ງໜ້າຖັດໄປຕ້ອງພາຕົວກັ່ນຕອງໄປນຳ ບໍ່ດັ່ງນັ້ນໜ້າ 2 ຈະເປັນຜົນທີ່ບໍ່ໄດ້ກັ່ນ
            ->withQueryString();

        return RevenueResource::collection($revenues);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('create', [Revenue::class, $project]);

        $data = $this->validated($request);
        $data['project_id'] = $project->id;

        $revenue = $this->revenues->create($data, (int) $request->user()->id);

        return RevenueResource::make($revenue->load(['payer', 'bank']))->response()->setStatusCode(201);
    }

    public function show(Revenue $revenue): RevenueResource
    {
        $this->authorize('view', $revenue);

        return RevenueResource::make($revenue->load(['payer', 'bank']));
    }

    public function update(Request $request, Revenue $revenue): RevenueResource
    {
        $this->authorize('update', $revenue);

        return RevenueResource::make(
            $this->revenues->update($revenue, $this->validated($request, $revenue))->load(['payer', 'bank'])
        );
    }

    public function destroy(Revenue $revenue): JsonResponse
    {
        $this->authorize('delete', $revenue);

        $revenue->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Revenue $revenue = null): array
    {
        $this->normalizeNumbers($request);

        $sometimes = $revenue !== null ? ['sometimes'] : [];

        return $request->validate([
            'received_date' => [...$sometimes, 'required', 'date'],
            'title' => [...$sometimes, 'required', 'string', 'max:255'],
            'currency_code' => [...$sometimes, 'required', 'string', 'size:3', Rule::exists('currencies', 'code')],

            // ຮັບເປັນຂໍ້ຄວາມ ("12,500,000") ແລ້ວແປງຜ່ານ Money —
            // ຫ້າມຮັບເປັນ float ເດັດຂາດ (docs/architecture.md ພາກ 2.1.ກ).
            // ຮູບແບບທົດສະນິຍົມທຳມະດາເທົ່ານັ້ນ: bcmath ຮັບ "1e5" ບໍ່ໄດ້ ແລະ ຖ້າປ່ອຍ
            // ໃຫ້ຜ່ານໄປ ມັນຈະລົ້ມຢູ່ DecimalString ເປັນ 500 ແທນ 422 (ຄືກັບ ExpenseRequest)
            'amount' => [...$sometimes, 'required', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0,0.0,0.00'],

            // ຜູ້ໃຊ້ອາດແລກເງິນຈິງໃນອັດຕາທີ່ຕ່າງຈາກອັດຕາທາງການ ຈຶ່ງໃສ່ເອງໄດ້
            'fx_rate_per_usd' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0'],
            // ອັດຕາເປັນກີບຕາມທີ່ຟອມສະແດງ (LAK = ກີບຕໍ່ 1 USD, THB = ກີບຕໍ່ 1 ບາດ)
            'fx_rate_lak' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0', 'prohibits:fx_rate_per_usd'],

            'payer_id' => ['nullable', 'integer', Rule::exists('payers', 'id')],
            // ທະນາຄານທີ່ເງິນເຂົ້າ — ຄູ່ກັບໃບໂອນທີ່ແນບເປັນຫຼັກຖານ
            'bank_id' => ['nullable', 'integer', Rule::exists('banks', 'id')],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.regex' => 'ຈຳນວນເງິນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 12500000 ຫຼື 12,500,000',
            'amount.not_in' => 'ຈຳນວນເງິນຕ້ອງບໍ່ເປັນສູນ',
            'fx_rate_per_usd.regex' => 'ອັດຕາແລກປ່ຽນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 21760 ຫຼື 32.22',
            'fx_rate_lak.regex' => 'ອັດຕາແລກປ່ຽນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 22239 ຫຼື 675.48',
            'currency_code.exists' => 'ບໍ່ຮູ້ຈັກສະກຸນເງິນນີ້',
        ]);
    }

    /**
     * ຊ່ອງຕົວເລກຢູ່ຟອມ (`NumericField`) ຜູກ v-model ກັບ**ຂໍ້ຄວາມທີ່ຈັດກຸ່ມຫຼັກພັນແລ້ວ**
     * ຈຶ່ງສົ່ງມາເປັນ "200,000" ແລະ "22,239" — ຄືກັບທີ່ຄົນພິມຈິງ.
     *
     * ຕ້ອງລ້າງກ່ອນກວດ ບໍ່ດັ່ງນັ້ນ `DecimalString::assert()` ຈະໂຍນ
     * InvalidArgumentException ຢູ່ຊັ້ນ Domain ແທນທີ່ຈະເປັນ 422 ທີ່ອ່ານເຂົ້າໃຈໄດ້.
     * ນີ້ຄືສິ່ງດຽວກັນທີ່ `ExpenseRequest::prepareForValidation()` ເຮັດໃຫ້ຝັ່ງລາຍຈ່າຍ.
     */
    private function normalizeNumbers(Request $request): void
    {
        $this->stripGrouping($request, 'amount', 'fx_rate_per_usd', 'fx_rate_lak');

        if (is_string($currency = $request->input('currency_code'))) {
            $request->merge(['currency_code' => strtoupper(trim($currency))]);
        }
    }

    /**
     * ຈຳນວນແຖວຕໍ່ໜ້າ.
     *
     * `?all=1` = ໜ້າຈໍຈະກັ່ນຕອງ ແລະ ແບ່ງໜ້າເອງ ຈຶ່ງຂໍທັງໂຄງການມາເທື່ອດຽວ
     * (ຍັງມີເພດານ — ເບິ່ງ ALL_ROWS_CAP). ນອກນັ້ນບີບ `per_page` ທີ່ມາຈາກ
     * client ໃຫ້ຢູ່ໃນຂອບເຂດ: ເມື່ອກ່ອນບໍ່ມີເພດານເລີຍ ແລະ `?per_page=100000`
     * ຈະດຶງທັງຕາຕະລາງມາສ້າງເປັນ Resource ພ້ອມກັນຈົນໜ່ວຍຄວາມຈຳໝົດ.
     * 0 ຫຼື ຕິດລົບກໍຕ້ອງກັນຄືກັນ.
     */
    private function perPage(Request $request): int
    {
        if ($request->boolean('all')) {
            return self::ALL_ROWS_CAP;
        }

        return max(min($request->integer('per_page', 50), self::MAX_PER_PAGE), 1);
    }

    /**
     * ຮຽງຕາມຖັນທີ່ຜູ້ໃຊ້ກົດຢູ່ຫົວຕາຕະລາງ.
     *
     * ທັງ `$sort` ແລະ `$direction` ຜ່ານ validate ມາແລ້ວ ແຕ່ຢູ່ນີ້ຍັງແຍກສອງທາງ
     * ດ້ວຍ `===` ອີກຊັ້ນ: ຄ່າທີ່ໄປຮອດ SQL ຈຶ່ງເປັນຂໍ້ຄວາມທີ່ຂຽນໄວ້ໃນໄຟລ໌ນີ້
     * ລ້ວນໆ ບໍ່ແມ່ນຂໍ້ຄວາມທີ່ມາຈາກ request — ຄວາມປອດໄພບໍ່ຂຶ້ນກັບ rule ທີ່
     * ຢູ່ຫ່າງອອກໄປ ແລະ ອາດຖືກແກ້ພາຍຫຼັງ.
     *
     * @param  Builder<Revenue>  $query
     * @return Builder<Revenue>
     */
    private function applySort(Builder $query, string $sort, string $direction): Builder
    {
        if ($sort === 'status') {
            return $direction === 'desc'
                ? $query->orderByRaw(self::STATUS_SORT_CASE.' desc')
                : $query->orderByRaw(self::STATUS_SORT_CASE.' asc');
        }

        return $query->orderBy(self::SORTABLE[$sort], $direction === 'desc' ? 'desc' : 'asc');
    }

    /** ລຶບເຄື່ອງໝາຍຈັດກຸ່ມຫຼັກພັນອອກ: "12,500,000" → "12500000" */
    private function stripGrouping(Request $request, string ...$fields): void
    {
        foreach ($fields as $field) {
            if (is_string($value = $request->input($field))) {
                $request->merge([$field => str_replace([',', ' ', "\u{00A0}"], '', $value)]);
            }
        }
    }

    /**
     * ແປງຄ່າຂອບເຂດທີ່ຄົນພິມ ("1,000.50") ເປັນ USD cents ສຳລັບປຽບທຽບກັບ
     * `amount_usd_minor`.
     *
     * ຜ່ານ `Money` ບໍ່ແມ່ນ `(int) ($v * 100)` — ຄູນດ້ວຍ float ຄືປະຕູຫຼັງທີ່
     * ບັນຫາປັດເສດຂອງ Excel ຈະກັບເຂົ້າມາ (docs/architecture.md ພາກ 2.1.ກ).
     */
    private function usdMinor(string $amount): int
    {
        return Money::fromDecimal($amount, 'USD', Currency::decimalsFor('USD'))->minor;
    }
}

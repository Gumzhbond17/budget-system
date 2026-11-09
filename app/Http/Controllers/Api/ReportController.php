<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseReportRequest;
use App\Http\Requests\LedgerReportRequest;
use App\Http\Requests\RevenueReportRequest;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\RevenueResource;
use App\Models\Expense;
use App\Models\Project;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\GeneralLedgerService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportPdfLink;
use App\Services\Reports\RevenueReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Endpoint ລວມສຳລັບ chart — ຄິດຢູ່ backend ທັງໝົດເພື່ອໃຫ້ chart ໃນ Vue
 * ກັບ PDF ໄດ້ຕົວເລກດຽວກັນສະເໝີ (ພາກ 5.2).
 */
final class ReportController extends Controller
{
    public function __construct(
        private ExpenseReportService $reports,
        private RevenueReportService $revenueReports,
        private GeneralLedgerService $ledger,
    ) {}

    public function summary(Request $request, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->summary($project, $this->filters($request, $project)),
        ]);
    }

    public function byCategory(Request $request, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->byCategory($project, $this->filters($request, $project)),
        ]);
    }

    public function byPayer(Request $request, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->byPayer($project, $this->filters($request, $project)),
        ]);
    }

    /**
     * ສະຫຼຸບ **ລາຍຮັບ** ຕາມແຫຼ່ງທຶນ — ຄູ່ຂອງ {@see self::byPayer()} ຄົນລະຝັ່ງ.
     *
     * ມີແຍກຕ່າງຫາກທັງທີ່ `reports/revenues` ກໍຄືນ `by_payer` ຢູ່ແລ້ວ ເພາະ
     * ອັນນັ້ນເປັນລາຍງານສະບັບເຕັມ: ມັນດຶງທຸກແຖວອອກມາເປັນ Resource ແລະ ເຊັນ
     * URL ຂອງ PDF ນຳ. ໜ້າຫຼັກຕ້ອງການແຕ່ຕົວເລກລວມສຳລັບວົງກົມ ແລະ ໂຫຼດຄືນ
     * ອັດຕະໂນມັດເລື້ອຍໆ — ຈ່າຍລາຄານັ້ນທຸກຮອບບໍ່ຄຸ້ມ.
     *
     * ໃຊ້ RevenueReportRequest (ບໍ່ແມ່ນ `filters()`) ເພາະອັນນັ້ນກວດສິດກັບ
     * Expense — ລາຍງານລາຍຮັບຕ້ອງກວດກັບ Revenue.
     */
    public function revenueByPayer(RevenueReportRequest $request, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->revenueReports->byPayer(
                $project,
                ReportFilters::fromArray($request->validated())
            ),
        ]);
    }

    public function monthlyTrend(Request $request, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->monthlyTrend($project, $this->filters($request, $project)),
        ]);
    }

    public function budgetVsActual(Request $request, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->budgetVsActual($project, $this->filters($request, $project)),
        ]);
    }

    /**
     * ລາຍງານລາຍຈ່າຍສະບັບເຕັມ — ຍອດລວມ + ສະຫຼຸບຕາມຜູ້ຈ່າຍ + ທຸກແຖວ ໃນ request ດຽວ.
     *
     * ຄືນທັງສາມສ່ວນພ້ອມກັນ (ບໍ່ແມ່ນສາມ endpoint) ເພື່ອວ່າສິ່ງທີ່ໜ້າຈໍສະແດງ ກັບ
     * ສິ່ງທີ່ປຸ່ມ "ພິມ PDF" ສ້າງ ມາຈາກຕົວກັ່ນຕອງອັນດຽວກັນສະເໝີ — ຖ້າແຍກກັນ
     * ຄົນຈະພິມລາຍງານທີ່ຕົວເລກບໍ່ກົງກັບທີ່ຕົນເອງເບິ່ງຢູ່.
     *
     * ບໍ່ແບ່ງໜ້າ: ໜ້ານີ້ຄື "ຮ່າງຂອງໃບທີ່ຈະພິມ" ບໍ່ແມ່ນຕາຕະລາງໄວ້ເລື່ອນເບິ່ງ.
     */
    public function expenses(ExpenseReportRequest $request, Project $project): JsonResponse
    {
        $filters = ReportFilters::fromArray($request->validated());
        $max = (int) config('pdf.max_rows');
        $rowCount = $this->reports->rowCount($project, $filters);

        return response()->json([
            'data' => [
                'summary' => $this->reports->summary($project, $filters),
                'by_payer' => $this->reports->byPayer($project, $filters),
                // ເກີນເພດານ = ບໍ່ດຶງແຖວເລີຍ ແລ້ວໃຫ້ໜ້າຈໍບອກໃຫ້ແຄບຕົວກັ່ນຕອງ —
                // ຄືກັບທີ່ ReportExportController ປະຕິເສດ ຈຶ່ງບໍ່ມີກໍລະນີທີ່
                // ໜ້າຈໍສະແດງໄດ້ ແຕ່ພິມບໍ່ໄດ້
                'expenses' => $rowCount > $max
                    ? []
                    : ExpenseResource::collection($this->reports->detailedList($project, $filters))->resolve(),
                'row_count' => $rowCount,
                'max_rows' => $max,
                'pdf_url' => $this->pdfUrl(
                    'reports.export.expenses-pdf', $request, $project, $rowCount, $max
                ),
            ],
        ]);
    }

    /**
     * ລາຍງານລາຍຮັບສະບັບເຕັມ — ຄູ່ຂອງ {@see self::expenses()} ດ້ວຍເຫດຜົນດຽວກັນ:
     * ຍອດລວມ, ສະຫຼຸບຕາມແຫຼ່ງທຶນ ແລະ ທຸກແຖວ ມາຈາກ request ດຽວ ຈຶ່ງບໍ່ມີທາງທີ່
     * ໜ້າຈໍກັບ PDF ຈະສະແດງຄົນລະຕົວເລກ.
     */
    public function revenues(RevenueReportRequest $request, Project $project): JsonResponse
    {
        $filters = ReportFilters::fromArray($request->validated());
        $max = (int) config('pdf.max_rows');
        $rowCount = $this->revenueReports->rowCount($project, $filters);

        return response()->json([
            'data' => [
                'summary' => $this->revenueReports->summary($project, $filters),
                'by_payer' => $this->revenueReports->byPayer($project, $filters),
                'revenues' => $rowCount > $max
                    ? []
                    : RevenueResource::collection(
                        $this->revenueReports->detailedList($project, $filters)
                    )->resolve(),
                'row_count' => $rowCount,
                'max_rows' => $max,
                'pdf_url' => $this->pdfUrl(
                    'reports.export.revenues-pdf', $request, $project, $rowCount, $max
                ),
            ],
        ]);
    }

    /**
     * ລາຍງານລາຍຮັບ-ລາຍຈ່າຍ: ສ່ວນສະຫຼຸບ (ຕໍ່ເດືອນ) + ສ່ວນລະອຽດ (ຍອດຄົງເຫຼືອແລ່ນ).
     *
     * ຕ່າງຈາກ `/ledger` ຢູ່ຈຸດດຽວ: ອັນນີ້ຄິດສ່ວນສະຫຼຸບຕໍ່ເດືອນໃຫ້ນຳ ແລະ ບັງຄັບ
     * ເພດານແຖວຄືກັນກັບ PDF — ໜ້າ /ledger ຄືໜ້າໃຊ້ວຽກປະຈຳວັນ ສ່ວນອັນນີ້ຄື
     * "ຮ່າງຂອງໃບທີ່ຈະພິມ".
     *
     * ສ່ວນສະຫຼຸບຄິດຈາກແຖວຊຸດດຽວກັນກັບສ່ວນລະອຽດ ຈຶ່ງບໍ່ມີທາງບໍ່ກົງກັນ.
     */
    public function ledger(LedgerReportRequest $request, Project $project): JsonResponse
    {
        $filters = ReportFilters::fromArray($request->validated());
        $max = (int) config('pdf.max_rows');
        $rowCount = $this->ledger->rowCount($project, $filters);

        if ($rowCount > $max) {
            // ເກີນເພດານ = ບໍ່ດຶງແຖວເລີຍ ແຕ່ຍັງຕ້ອງຄືນ totals ໃຫ້ໜ້າຈໍບອກໄດ້ວ່າ
            // ຫຼາຍປານໃດ. ດຶງ totals ດ້ວຍ query ນັບ ບໍ່ແມ່ນດຶງແຖວທັງໝົດ.
            return response()->json([
                'data' => [
                    'entries' => [],
                    'monthly' => [],
                    'totals' => null,
                    'row_count' => $rowCount,
                    'max_rows' => $max,
                    'pdf_url' => null,
                ],
            ]);
        }

        $ledger = $this->ledger->forProject($project, $filters);

        return response()->json([
            'data' => [
                'entries' => $ledger['entries'],
                'monthly' => $this->ledger->monthlySummary($ledger['entries']),
                'totals' => $ledger['totals'],
                'row_count' => $rowCount,
                'max_rows' => $max,
                'pdf_url' => $this->pdfUrl(
                    'reports.export.ledger-pdf', $request, $project, $rowCount, $max
                ),
            ],
        ]);
    }

    /**
     * URL ທີ່ເຊັນແລ້ວສຳລັບເປີດ PDF ໃນແທັບໃໝ່ — null ເມື່ອພິມບໍ່ໄດ້.
     *
     * ສ້າງຢູ່ server ຈາກ **ຕົວກັ່ນຕອງທີ່ຫາກໍຕອບໄປ** ບໍ່ແມ່ນໃຫ້ Vue ປະກອບ URL ເອງ:
     * ນອກຈາກຈະແກ້ບັນຫາ 401 ຕອນເປີດແທັບໃໝ່ (ເບິ່ງ ReportPdfLink) ຍັງເຮັດໃຫ້
     * ໃບທີ່ພິມກັບຕົວເລກທີ່ເຫັນມາຈາກຊຸດຕົວກັ່ນຕອງອັນດຽວກັນແທ້ໆ ບໍ່ແມ່ນສອງຊຸດ
     * ທີ່ຂຽນໃຫ້ຄືກັນໄວ້.
     *
     * null ເມື່ອບໍ່ມີແຖວ ຫຼື ເກີນເພດານ — ໜ້າຈໍໃຊ້ຄ່ານີ້ຕັດສິນວ່າຈະເປີດປຸ່ມພິມບໍ
     * ຈຶ່ງບໍ່ມີກໍລະນີທີ່ປຸ່ມກົດໄດ້ແຕ່ server ປະຕິເສດ.
     */
    private function pdfUrl(
        string $route,
        FormRequest $request,
        Project $project,
        int $rowCount,
        int $max,
    ): ?string {
        if ($rowCount === 0 || $rowCount > $max) {
            return null;
        }

        return ReportPdfLink::make($route, $project, $request->user(), $request->validated());
    }

    public function missingEvidence(Request $request, Project $project): AnonymousResourceCollection
    {
        $filters = $this->filters($request, $project);

        return ExpenseResource::collection(
            $this->reports->missingEvidence($project, $filters, $request->integer('per_page', 50))
        );
    }

    private function filters(Request $request, Project $project): ReportFilters
    {
        $this->authorize('viewAny', [Expense::class, $project]);

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'category_id' => ['nullable', 'integer'],
            'payer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
            // 'all' ຫຼື array ຂອງສະຖານະ — ຄ່າຕັ້ງຕົ້ນຄື approved ເທົ່ານັ້ນ
            'statuses' => ['nullable', function (string $attribute, mixed $value, callable $fail): void {
                if ($value !== 'all' && ! is_array($value)) {
                    $fail('statuses ຕ້ອງເປັນ "all" ຫຼື array ຂອງສະຖານະ');
                }
            }],
            'statuses.*' => ['in:draft,submitted,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return ReportFilters::fromArray($validated);
    }
}

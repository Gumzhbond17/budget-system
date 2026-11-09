<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Project;
use App\Services\Reports\GeneralLedgerService;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ບັນຊີແຍກປະເພດ — ລາຍຮັບ ແລະ ລາຍຈ່າຍໃນລຳດັບເວລາດຽວ ພ້ອມຍອດຄົງເຫຼືອແລ່ນ.
 *
 * ຍອດທັງໝົດຄິດຢູ່ backend ເພື່ອໃຫ້ໜ້າຈໍ, PDF ແລະ ການກວດສອບ ໄດ້ຕົວເລກດຽວກັນ.
 */
final class LedgerController extends Controller
{
    public function __construct(private GeneralLedgerService $ledger) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        // ໃຊ້ ExpensePolicy::viewAny ເປັນປະຕູ — ເຫັນ GL ໄດ້ = ເປັນສະມາຊິກໂຄງການ.
        // RevenuePolicy ມີກົດດຽວກັນ ຈຶ່ງບໍ່ຕ້ອງກວດສອງເທື່ອ.
        $this->authorize('viewAny', [Expense::class, $project]);

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'statuses' => ['nullable', function (string $attribute, mixed $value, callable $fail): void {
                if ($value !== 'all' && ! is_array($value)) {
                    $fail('statuses ຕ້ອງເປັນ "all" ຫຼື array ຂອງສະຖານະ');
                }
            }],
            'statuses.*' => ['in:draft,submitted,approved,rejected'],
        ]);

        return response()->json([
            'data' => $this->ledger->forProject($project, ReportFilters::fromArray($validated)),
        ]);
    }
}

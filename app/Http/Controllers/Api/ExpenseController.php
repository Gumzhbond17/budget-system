<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Project;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class ExpenseController extends Controller
{
    /** ຕ້ອງກົງກັບຕົວເລືອກສູງສຸດຂອງການແບ່ງໜ້າຢູ່ໜ້າຈໍ (20/50/100/150) */
    private const MAX_PER_PAGE = 150;

    /**
     * ເພດານຂອງ `?all=1` — ໜ້າຈໍໂຫຼດທັງໂຄງການມາໃຫ້ຕາຕະລາງກັ່ນຕອງ, ຮຽງ ແລະ
     * ແບ່ງໜ້າເອງຢູ່ browser. ເຫດຜົນດຽວກັນກັບ RevenueController.
     */
    private const ALL_ROWS_CAP = 5000;

    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Expense::class, $project]);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'category_id' => ['nullable', 'integer'],
            'payer_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,submitted,approved,rejected'],
            'has_evidence' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'all' => ['nullable', 'boolean'],
        ]);

        $expenses = $project->expenses()
            // ໂຫຼດລ່ວງໜ້າ — ExpenseResource ອ້າງເຖິງສາມ relation ນີ້
            ->with(['category:id,code,name_lo,color', 'payer:id,name', 'payee:id,name', 'bank:id,code,name_lo,short_name'])
            ->when($filters['date_from'] ?? null, fn ($q, $d) => $q->where('expense_date', '>=', $d))
            ->when($filters['date_to'] ?? null, fn ($q, $d) => $q->where('expense_date', '<=', $d))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['payer_id'] ?? null, fn ($q, $id) => $q->where('payer_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(
                isset($filters['has_evidence']),
                fn ($q) => $q->where('attachments_count', $filters['has_evidence'] ? '>' : '=', 0)
            )
            // LIKE ບໍ່ໃຊ້ index ແຕ່ພຽງພໍທີ່ຂະໜາດນີ້ — ພາກ 2.1.ງ ທາງເລືອກ #3
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('doc_no', 'like', "%{$term}%")
            ))
            // ລ່າສຸດທີ່ **ບັນທຶກ** ຂຶ້ນກ່ອນ ບໍ່ແມ່ນລ່າສຸດຕາມວັນທີຈ່າຍ — ບັນທຶກ
            // ຍ້ອນຫຼັງແລ້ວຕ້ອງເຫັນແຖວທີ່ຫາກໍໃສ່ຢູ່ເທິງສຸດທັນທີ ບໍ່ຕ້ອງໄປຫາ
            // ກາງຕາຕະລາງ. `id` ຕໍ່ທ້າຍເປັນຕົວຕັດສິນ: `created_at` ລະອຽດພຽງ
            // ວິນາທີ ຫຼາຍແຖວທີ່ໃສ່ຕິດກັນຈະສະຫຼັບບ່ອນກັນລະຫວ່າງໜ້າ
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $request->boolean('all')
                    ? self::ALL_ROWS_CAP
                    : ($filters['per_page'] ?? 25)
            )
            ->withQueryString();

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request, Project $project): JsonResponse
    {
        $expense = $this->expenses->create(
            [...$request->validated(), 'project_id' => $project->id],
            (int) $request->user()->id,
        );

        return ExpenseResource::make($expense->load(['category', 'payer', 'payee', 'bank']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);

        return ExpenseResource::make($expense->load(['category', 'payer', 'payee', 'bank']));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $updated = $this->expenses->update($expense, $request->validated());

        return ExpenseResource::make($updated->load(['category', 'payer', 'payee', 'bank']));
    }

    public function destroy(Expense $expense): Response
    {
        $this->authorize('delete', $expense);

        // soft delete — ຂໍ້ມູນການເງິນຫ້າມລຶບຖາວອນ (ພາກ 9)
        $expense->delete();

        return response()->noContent();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetItemResource;
use App\Models\BudgetItem;
use App\Models\Project;
use App\Services\BudgetItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * BOQ ຂອງໂຄງການ (sheet `ຕົ້ນທຶນ`).
 *
 * ຍອດຄິດຢູ່ BudgetItemService ບ່ອນດຽວ — controller ບໍ່ຄິດເລກເອງເລີຍ.
 */
final class BudgetItemController extends Controller
{
    public function __construct(private BudgetItemService $budgetItems) {}

    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [BudgetItem::class, $project]);

        $items = $project->budgetItems()
            ->with('category:id,name_lo,color')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return BudgetItemResource::collection($items);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('create', [BudgetItem::class, $project]);

        $item = $this->budgetItems->create([
            ...$this->validated($request),
            'project_id' => $project->id,
        ]);

        return BudgetItemResource::make($item->load('category'))->response()->setStatusCode(201);
    }

    public function show(BudgetItem $budgetItem): BudgetItemResource
    {
        $this->authorize('view', $budgetItem);

        return BudgetItemResource::make($budgetItem->load('category'));
    }

    public function update(Request $request, BudgetItem $budgetItem): BudgetItemResource
    {
        $this->authorize('update', $budgetItem);

        $updated = $this->budgetItems->update($budgetItem, $this->validated($request, $budgetItem));

        return BudgetItemResource::make($updated->load('category'));
    }

    public function destroy(BudgetItem $budgetItem): Response
    {
        $this->authorize('delete', $budgetItem);

        // ງົບປະມານແມ່ນແຜນ ບໍ່ແມ່ນບັນທຶກການເງິນ — ລຶບຖາວອນໄດ້
        $budgetItem->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BudgetItem $item = null): array
    {
        foreach (['unit_price', 'quantity', 'fx_rate_per_usd'] as $field) {
            if (is_string($value = $request->input($field))) {
                $request->merge([$field => str_replace([',', ' '], '', $value)]);
            }
        }

        $sometimes = $item !== null ? ['sometimes'] : [];

        return $request->validate([
            'name' => [...$sometimes, 'required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'unit' => ['nullable', 'string', 'max:20'],
            // ຮູບແບບທົດສະນິຍົມທຳມະດາເທົ່ານັ້ນ — bcmath ຮັບ "1e5" ບໍ່ໄດ້
            'quantity' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0,0.0,0.00'],
            'currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'unit_price' => [...$sometimes, 'required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'fx_rate_per_usd' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0'],
            'rate_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'quantity.regex' => 'ຈຳນວນຕ້ອງເປັນຕົວເລກທຳມະດາ',
            'quantity.not_in' => 'ຈຳນວນຕ້ອງຫຼາຍກວ່າ 0',
            'unit_price.regex' => 'ລາຄາຕໍ່ຫົວໜ່ວຍຕ້ອງເປັນຕົວເລກທຳມະດາ',
        ]);
    }
}

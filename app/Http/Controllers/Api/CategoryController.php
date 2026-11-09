<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * `destroy` ລຶບໄດ້ສະເພາະໝວດທີ່ **ບໍ່ມີໃຜອ້າງເຖິງ** ເທົ່ານັ້ນ.
 *
 * FK ຂອງ `expenses.category_id` ເປັນ `nullOnDelete` ດັ່ງນັ້ນການລຶບໝວດທີ່ມີ
 * ລາຍຈ່າຍຢູ່ຈະລຶບ "ລາຍການນີ້ເຄີຍຢູ່ໝວດໃດ" ອອກຈາກປະຫວັດແບບງຽບໆ — ໃນລະບົບ
 * ບັນຊີນັ້ນຄືການທຳລາຍຫຼັກຖານ. ດັ່ງນັ້ນຈຶ່ງກວດກ່ອນ: ມີລາຍຈ່າຍ ຫຼື ໝວດລູກ
 * ຢູ່ນຳ → 409 ພ້ອມບອກໃຫ້ໃຊ້ `is_active = false` ແທນ.
 *
 * ໝວດທີ່ຫາກໍສ້າງແລ້ວພິມຜິດບໍ່ມີຫຍັງອ້າງເຖິງ — ລຶບຖິ້ມໄດ້ ບໍ່ເສຍປະຫວັດຫຍັງ.
 */
final class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $search = trim((string) $request->string('search'));

        $categories = ExpenseCategory::query()
            // ໜ້າຈັດການໝວດຕ້ອງຮູ້ວ່າໝວດໃດລຶບໄດ້ ກ່ອນຈະກົດປຸ່ມລຶບ
            ->withCount(['expenses', 'children'])
            ->when($request->boolean('tree'), fn ($q) => $q->whereNull('parent_id')->with('children'))
            ->when(! $request->has('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($search !== '', fn ($q) => $q->where(
                fn ($sub) => $sub->where('code', 'like', "%{$search}%")
                    ->orWhere('name_lo', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
            ))
            ->orderBy('sort_order')
            ->orderBy('name_lo')
            ->get();

        return ExpenseCategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $category = ExpenseCategory::query()->create($this->validated($request));

        return ExpenseCategoryResource::make($category->loadCount(['expenses', 'children']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ExpenseCategory $category): ExpenseCategoryResource
    {
        $this->authorize('view', $category);

        return ExpenseCategoryResource::make($category->load('children'));
    }

    public function update(Request $request, ExpenseCategory $category): ExpenseCategoryResource
    {
        $this->authorize('update', $category);

        $category->update($this->validated($request, $category));

        return ExpenseCategoryResource::make($category->loadCount(['expenses', 'children']));
    }

    public function destroy(ExpenseCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $expenses = $category->expenses()->count();
        $children = $category->children()->count();

        if ($expenses > 0 || $children > 0) {
            // 409 ບໍ່ແມ່ນ 403: ບໍ່ແມ່ນເລື່ອງສິດ ແຕ່ເປັນສະຖານະຂອງຂໍ້ມູນ
            return response()->json([
                'message' => $expenses > 0
                    ? "ລຶບບໍ່ໄດ້ — ມີລາຍຈ່າຍ {$expenses} ລາຍການຢູ່ໝວດນີ້. ຢຸດໃຊ້ດ້ວຍການປິດ 'ໃຊ້ງານຢູ່' ແທນ."
                    : "ລຶບບໍ່ໄດ້ — ມີໝວດຍ່ອຍ {$children} ໝວດຢູ່ໃຕ້ໝວດນີ້. ຍ້າຍ ຫຼື ລຶບໝວດຍ່ອຍກ່ອນ.",
            ], 409);
        }

        $category->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        $sometimes = $category !== null ? ['sometimes'] : [];

        return $request->validate([
            'code' => [...$sometimes, 'required', 'string', 'max:40',
                Rule::unique('expense_categories', 'code')->ignore($category)],
            'name_lo' => [...$sometimes, 'required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            // ໝວດແມ່ຂອງຕົນເອງເປັນ loop — ຫ້າມ
            'parent_id' => ['nullable', 'integer', 'exists:expense_categories,id',
                Rule::notIn($category !== null ? [$category->id] : [])],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'color.regex' => 'ສີຕ້ອງເປັນຮູບແບບ #RRGGBB',
            'parent_id.not_in' => 'ໝວດເປັນແມ່ຂອງຕົນເອງບໍ່ໄດ້',
        ]);
    }
}

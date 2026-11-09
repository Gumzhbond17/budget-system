<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayerResource;
use App\Models\Payer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * ຜູ້ຈ່າຍ / ແຫຼ່ງທຶນ. `name` ເປັນ unique ຢູ່ລະດັບ DB ເພື່ອກັນ
 * "SSS" vs "SSS " vs "Sengsamai/Saengsamai" ທີ່ພົບໃນ Excel ເດີມ.
 *
 * `destroy` ລຶບໄດ້ສະເພາະຜູ້ຈ່າຍທີ່ **ບໍ່ມີໃຜອ້າງເຖິງ** ເທົ່ານັ້ນ —
 * `expenses.payer_id` ແລະ `revenues.payer_id` ເປັນ `nullOnDelete` ຈຶ່ງຈະລຶບ
 * "ໃຜເປັນຄົນອອກເງິນ" ອອກຈາກປະຫວັດແບບງຽບໆ. ມີລາຍການຢູ່ນຳ → 409 ພ້ອມບອກ
 * ໃຫ້ໃຊ້ `is_active = false` ແທນ. ແຖວທີ່ຫາກໍພິມຜິດລຶບຖິ້ມໄດ້ ບໍ່ເສຍຫຍັງ.
 */
final class PayerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payer::class);

        $search = trim((string) $request->string('search'));

        $payers = Payer::query()
            // ໜ້າຈັດການຕ້ອງຮູ້ວ່າແຖວໃດລຶບໄດ້ ກ່ອນຈະສະແດງປຸ່ມລຶບ
            ->withCount(['expenses', 'revenues'])
            ->when(! $request->has('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($search !== '', fn ($q) => $q->where(
                fn ($sub) => $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->get();

        return PayerResource::collection($payers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Payer::class);

        $payer = Payer::query()->create($this->validated($request));

        return PayerResource::make($payer->loadCount(['expenses', 'revenues']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payer $payer): PayerResource
    {
        $this->authorize('view', $payer);

        return PayerResource::make($payer);
    }

    public function update(Request $request, Payer $payer): PayerResource
    {
        $this->authorize('update', $payer);

        $payer->update($this->validated($request, $payer));

        return PayerResource::make($payer->loadCount(['expenses', 'revenues']));
    }

    public function destroy(Payer $payer): JsonResponse
    {
        $this->authorize('delete', $payer);

        $used = $payer->expenses()->count() + $payer->revenues()->count();

        if ($used > 0) {
            // 409 ບໍ່ແມ່ນ 403: ບໍ່ແມ່ນເລື່ອງສິດ ແຕ່ເປັນສະຖານະຂອງຂໍ້ມູນ
            return response()->json([
                'message' => "ລຶບບໍ່ໄດ້ — ມີ {$used} ລາຍການອ້າງເຖິງຜູ້ຈ່າຍນີ້. ຢຸດໃຊ້ດ້ວຍການປິດ 'ໃຊ້ງານຢູ່' ແທນ.",
            ], 409);
        }

        $payer->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Payer $payer = null): array
    {
        // ຕັດ space ຫົວ-ທ້າຍກ່ອນກວດ unique ບໍ່ດັ່ງນັ້ນ "SSS " ຈະຜ່ານໄປເປັນແຖວທີສອງ
        if (is_string($name = $request->input('name'))) {
            $request->merge(['name' => trim($name)]);
        }

        $sometimes = $payer !== null ? ['sometimes'] : [];

        return $request->validate([
            'name' => [...$sometimes, 'required', 'string', 'max:120',
                Rule::unique('payers', 'name')->ignore($payer)],
            'type' => ['nullable', 'in:company,individual'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'ມີຜູ້ຈ່າຍຊື່ນີ້ຢູ່ແລ້ວ',
        ]);
    }
}

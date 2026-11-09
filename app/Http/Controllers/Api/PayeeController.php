<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayeeResource;
use App\Models\Payee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * ຜູ້ຮັບເງິນ. `bank_account_no` ຮັບເຂົ້າໄດ້ ແຕ່ **ບໍ່ເຄີຍສົ່ງກັບອອກເຕັມ** —
 * response ມີແຕ່ `bank_account_no_masked` (ພາກ 9).
 *
 * `destroy` ລຶບໄດ້ສະເພາະຜູ້ຮັບເງິນທີ່ບໍ່ມີລາຍຈ່າຍອ້າງເຖິງ — ເຫດຜົນດຽວກັນ
 * ກັບ payers (`expenses.payee_id` ເປັນ `nullOnDelete`).
 */
final class PayeeController extends Controller
{
    /** ຜູ້ຮັບເງິນເປັນຕາຕະລາງທີ່ໃຫຍ່ຂຶ້ນເລື້ອຍໆ (ຮ້ານໃໝ່ທຸກເທື່ອທີ່ຈ່າຍ) */
    private const MAX_PER_PAGE = 200;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payee::class);

        $perPage = min($request->integer('per_page', 50), self::MAX_PER_PAGE);

        $payees = Payee::query()
            // ໜ້າຈັດການຕ້ອງຮູ້ວ່າແຖວໃດລຶບໄດ້ ກ່ອນຈະສະແດງປຸ່ມລຶບ
            ->withCount('expenses')
            ->when(! $request->has('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where(
                'name', 'like', '%'.$request->string('search').'%'
            ))
            ->orderBy('name')
            ->paginate(max($perPage, 1));

        return PayeeResource::collection($payees);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Payee::class);

        $payee = Payee::query()->create($this->validated($request));

        return PayeeResource::make($payee->loadCount('expenses'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payee $payee): PayeeResource
    {
        $this->authorize('view', $payee);

        return PayeeResource::make($payee);
    }

    public function update(Request $request, Payee $payee): PayeeResource
    {
        $this->authorize('update', $payee);

        $payee->update($this->validated($request, $payee));

        return PayeeResource::make($payee->loadCount('expenses'));
    }

    public function destroy(Payee $payee): JsonResponse
    {
        $this->authorize('delete', $payee);

        $used = $payee->expenses()->count();

        if ($used > 0) {
            return response()->json([
                'message' => "ລຶບບໍ່ໄດ້ — ມີລາຍຈ່າຍ {$used} ລາຍການຈ່າຍໃຫ້ຜູ້ຮັບເງິນນີ້. ຢຸດໃຊ້ດ້ວຍການປິດ 'ໃຊ້ງານຢູ່' ແທນ.",
            ], 409);
        }

        $payee->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Payee $payee = null): array
    {
        if (is_string($name = $request->input('name'))) {
            $request->merge(['name' => trim($name)]);
        }

        $sometimes = $payee !== null ? ['sometimes'] : [];

        return $request->validate([
            'name' => [...$sometimes, 'required', 'string', 'max:160'],
            'type' => ['nullable', 'in:company,individual,government'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'bank_account_no' => ['nullable', 'string', 'max:60'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}

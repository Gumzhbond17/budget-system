<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * ສະກຸນເງິນ — ເພີ່ມ/ແກ້/ລຶບໄດ້ ແຕ່ມີສອງຢ່າງທີ່ຫ້າມແຕະຫຼັງສ້າງແລ້ວ:
 *
 * 1. `decimal_places` ຄືຄວາມໝາຍຂອງ **ທຸກ** ຄ່າ minor ທີ່ເກັບໄວ້ແລ້ວ.
 *    ແກ້ LAK ຈາກ 0 ເປັນ 2 → ຍອດ 12,500,000 ກີບ ກາຍເປັນ 125,000 ກີບ ທັນທີ
 *    ທົ່ວລະບົບ ໂດຍທີ່ບໍ່ມີແຖວໃດຖືກແກ້ເລີຍ. ການປ່ຽນຕ້ອງຜ່ານ migration
 *    + ການແປງຂໍ້ມູນ ບໍ່ແມ່ນຜ່ານຟອມ.
 * 2. `code` ເປັນ primary key ແລະ ເປັນ FK ຢູ່ 5 ຕາຕະລາງ.
 *
 * ຟອມຈຶ່ງແກ້ໄດ້ແຕ່ຊື່/ສັນຍາລັກ/ລຳດັບ/ສະຖານະ. ສ້າງໃໝ່ຕັ້ງ decimal_places ໄດ້
 * ເສລີ ເພາະຍັງບໍ່ມີແຖວໃດອ້າງເຖິງ.
 */
final class CurrencyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::query()
            // ນັບການອ້າງອີງສະເພາະເມື່ອຖືກຂໍ: ຟອມທຸລະກຳດຶງ endpoint ນີ້ທຸກເທື່ອ
            // ທີ່ເປີດ ແລະ ບໍ່ໄດ້ໃຊ້ຄ່ານັບເລີຍ — ບໍ່ຕ້ອງຈ່າຍ 5 subquery ຟຣີ
            ->when($request->boolean('with_usage'), fn ($q) => $q->withCount($this->usageCounts()))
            // ໜ້າຕັ້ງຄ່າຄືບ່ອນດຽວທີ່ຕ້ອງເຫັນສະກຸນທີ່ຢຸດໃຊ້ ເພື່ອເປີດກັບຄືນໄດ້
            ->when(! $request->has('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return CurrencyResource::collection($currencies);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Currency::class);

        $currency = Currency::query()->create($this->validated($request));

        return CurrencyResource::make($this->withUsage($currency))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Currency $currency): CurrencyResource
    {
        $this->authorize('update', $currency);

        $currency->update($this->validated($request, $currency));

        return CurrencyResource::make($this->withUsage($currency));
    }

    public function destroy(Currency $currency): JsonResponse
    {
        $this->authorize('delete', $currency);

        // ສອງສະກຸນນີ້ບໍ່ແມ່ນ "ຂໍ້ມູນ" ແຕ່ເປັນໂຄງສ້າງຂອງການແປງເງິນ:
        //   USD = pivot ທີ່ທຸກ rate ອ້າງອີງ (config('currency.pivot'))
        //   LAK = ສະກຸນລາຍງານ ທີ່ CurrencyConverter::toLakMinor ຄິດໃສ່ທຸກແຖວ
        // ລຶບແລ້ວທຸກການບັນທຶກລາຍຮັບ/ລາຍຈ່າຍໃໝ່ຈະລົ້ມ ບໍ່ວ່າຈະເປັນສະກຸນໃດ.
        if (in_array($currency->code, $this->structuralCodes(), true)) {
            return response()->json([
                'message' => "ລຶບ {$currency->code} ບໍ່ໄດ້ — ເປັນສະກຸນຫຼັກທີ່ລະບົບໃຊ້ແປງເງິນທຸກລາຍການ.",
            ], 409);
        }

        $used = $currency->usageCount();

        if ($used > 0) {
            // 409 ບໍ່ແມ່ນ 403: ບໍ່ແມ່ນເລື່ອງສິດ ແຕ່ເປັນສະຖານະຂອງຂໍ້ມູນ
            return response()->json([
                'message' => "ລຶບບໍ່ໄດ້ — ມີ {$used} ລາຍການອ້າງເຖິງສະກຸນນີ້ (ລວມທັງອັດຕາແລກປ່ຽນ). "
                           ."ຢຸດໃຊ້ດ້ວຍການປິດ 'ໃຊ້ງານຢູ່' ແທນ.",
            ], 409);
        }

        $currency->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Currency $currency = null): array
    {
        if (is_string($code = $request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($code))]);
        }

        if ($currency === null) {
            return $request->validate([
                'code' => ['required', 'string', 'regex:/^[A-Z]{3}$/', 'unique:currencies,code'],
                'name_lo' => ['required', 'string', 'max:60'],
                'name_en' => ['required', 'string', 'max:60'],
                'symbol' => ['required', 'string', 'max:8'],
                // ບັງຄັບໃສ່ເອງ ບໍ່ມີ default: ນີ້ຄືຄ່າທີ່ຜິດແລ້ວແກ້ຄືນບໍ່ໄດ້
                'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
                'is_active' => ['nullable', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            ], [
                'code.regex' => 'ລະຫັດຕ້ອງເປັນຕົວອັກສອນອັງກິດ 3 ໂຕ ຕາມ ISO-4217 ເຊັ່ນ LAK, THB, USD',
                'code.unique' => 'ມີສະກຸນລະຫັດນີ້ຢູ່ແລ້ວ',
            ]);
        }

        return $request->validate([
            // ຮັບຄ່າເກົ່າຄືນມາໄດ້ (ຟອມສົ່ງທັງກ້ອນ) ແຕ່ຄ່າໃໝ່ຈະຖືກປະຕິເສດ
            'code' => ['sometimes', Rule::in([$currency->code])],
            'decimal_places' => ['sometimes', 'integer', Rule::in([$currency->decimal_places])],
            'name_lo' => ['sometimes', 'required', 'string', 'max:60'],
            'name_en' => ['sometimes', 'required', 'string', 'max:60'],
            'symbol' => ['sometimes', 'required', 'string', 'max:8'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'code.in' => 'ປ່ຽນລະຫັດສະກຸນບໍ່ໄດ້ — ມີ 5 ຕາຕະລາງອ້າງເຖິງມັນຢູ່. ສ້າງສະກຸນໃໝ່ແທນ.',
            'decimal_places.in' => 'ປ່ຽນຈຳນວນທົດສະນິຍົມບໍ່ໄດ້ — ຄ່ານີ້ຄືຄວາມໝາຍຂອງທຸກຍອດເງິນ '
                                 .'ທີ່ເກັບໄວ້ແລ້ວ. ປ່ຽນແລ້ວຍອດເກົ່າຈະຜິດທັນທີໂດຍບໍ່ມີແຖວໃດຖືກແກ້.',
        ]);
    }

    /** ນັບການອ້າງອີງແບບ subquery — soft delete ນັບນຳ ເພາະ FK ຍັງຊີ້ຢູ່ */
    private function withUsage(Currency $currency): Currency
    {
        return $currency->loadCount($this->usageCounts());
    }

    /**
     * @return array<string, \Closure>
     */
    private function usageCounts(): array
    {
        return collect(Currency::USAGE_RELATIONS)
            ->mapWithKeys(fn (string $relation): array => [
                $relation => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function structuralCodes(): array
    {
        return array_values(array_unique([(string) config('currency.pivot', 'USD'), 'LAK']));
    }
}

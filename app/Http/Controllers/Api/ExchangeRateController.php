<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Money\DecimalString;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * ຕາຕະລາງອ້າງອີງ — ບໍ່ແມ່ນຄ່າທີ່ລາຍງານໃຊ້.
 *
 * ແກ້ rate ຍ້ອນຫຼັງໄດ້ຢ່າງປອດໄພ ເພາະທຸກລາຍຈ່າຍ snapshot `fx_rate_per_usd`
 * ຂອງຕົນເອງໄວ້ແລ້ວ (ພາກ 2.1.ຂ) — ລາຍງານເດືອນທີ່ປິດບັນຊີໄປແລ້ວຈະບໍ່ຂະຍັບ.
 *
 * ໜ້າຈໍຕັ້ງອັດຕາເປັນກີບ (`rate_lak`): ແຖວ LAK = "1 USD = X ກີບ",
 * ແຖວ THB = "1 ບາດ = X ກີບ". ເບິ່ງ `withStoredRates()`.
 */
final class ExchangeRateController extends Controller
{
    public function __construct(private ExchangeRateService $fx) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $rates = ExchangeRate::query()
            // ໃຜເປັນຄົນຕັ້ງອັດຕາຄືສ່ວນໜຶ່ງຂອງຫຼັກຖານ — ໜ້າຕັ້ງອັດຕາປະຈຳວັນສະແດງ
            ->with('creator:id,name')
            ->when($request->string('currency')->isNotEmpty(), fn ($q) => $q->where(
                'currency_code', strtoupper((string) $request->string('currency'))
            ))
            ->when($request->date('date_from'), fn ($q, $d) => $q->where('effective_date', '>=', $d))
            ->when($request->date('date_to'), fn ($q, $d) => $q->where('effective_date', '<=', $d))
            // ວັນທີດຽວກັນຫຼາຍສະກຸນ → ຮຽງຕາມສະກຸນຕໍ່ ບໍ່ດັ່ງນັ້ນລຳດັບຈະສະຫຼັບໄປມາ
            // ລະຫວ່າງໜ້າ ແລະ ແຖວດຽວກັນຈະປາກົດສອງເທື່ອ / ຫາຍໄປ
            ->orderByDesc('effective_date')
            ->orderBy('currency_code')
            ->paginate(min($request->integer('per_page', 50), 200));

        return ExchangeRateResource::collection($rates);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExchangeRate::class);

        $validated = $this->validated($request);

        $rate = ExchangeRate::query()->create([
            ...$this->withStoredRates($validated),
            'created_by' => $request->user()?->id,
        ]);

        return ExchangeRateResource::make($rate)->response()->setStatusCode(201);
    }

    public function update(Request $request, ExchangeRate $exchangeRate): ExchangeRateResource
    {
        $this->authorize('update', $exchangeRate);

        $exchangeRate->update($this->withStoredRates($this->validated($request, $exchangeRate), $exchangeRate));

        return ExchangeRateResource::make($exchangeRate);
    }

    /**
     * Autofill ໃຫ້ຟອມ Vue (ພາກ 7.3) — ຫາ rate ຂອງວັນນັ້ນ ຫຼື ຖອຍຫຼັງຫາອັນຫຼ້າສຸດ.
     *
     * `effective_date` ຄືວັນທີຂອງແຖວທີ່ຖືກໃຊ້ຈິງ ບໍ່ແມ່ນວັນທີທີ່ຖາມ — ໜ້າຕັ້ງ
     * ອັດຕາປະຈຳວັນໃຊ້ຄ່ານີ້ບອກວ່າ "ມື້ນີ້ຕັ້ງແລ້ວ" ຫຼື "ຍັງໃຊ້ຂອງມື້ເກົ່າຢູ່".
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'date' => ['required', 'date'],
        ]);

        $currency = strtoupper($validated['currency']);
        $date = Carbon::parse($validated['date']);

        try {
            $rate = $this->fx->rateFor($currency, $date);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'rate' => null, 'rate_lak' => null], 404);
        }

        try {
            $rateLak = $this->fx->lakQuoteFor($currency, $date);
        } catch (RuntimeException) {
            // USD ມີ rate (1) ສະເໝີ ແຕ່ອາດຍັງບໍ່ມີອັດຕາ LAK ໃຫ້ຄິດເປັນກີບ
            $rateLak = null;
        }

        return response()->json([
            'rate' => $rate,
            // ອັດຕາເປັນກີບ — ສິ່ງທີ່ໜ້າຈໍສະແດງ ແລະ ເຕີມໃສ່ຟອມ.
            // `quote_code` ຄື "1 ໜ່ວຍຂອງຫຍັງ": ແຖວ LAK ຄື "1 USD = X ກີບ"
            'rate_lak' => $rateLak,
            'quote_code' => ExchangeRate::quoteCodeFor($currency),
            'currency' => $currency,
            // USD ບໍ່ມີແຖວໃນຕາຕະລາງ (rate = 1 ສະເໝີ) → null ບໍ່ແມ່ນວັນທີປອມ
            'effective_date' => ExchangeRate::query()
                ->effectiveOn($currency, $date)
                ->value('effective_date')?->toDateString(),
        ]);
    }

    /**
     * ກວດ rate ທີ່ຜູ້ໃຊ້ພິມເອງ ກ່ອນ submit ຟອມ (ພາກ 7.3 `checkRate`).
     *
     * ຄືນ 200 ສະເໝີພ້ອມ `{ok, message}` — ນີ້ຄື**ຄຳເຕືອນສົດ** ບໍ່ແມ່ນການປະຕິເສດ.
     * ການບັງຄັບຈິງເກີດຢູ່ StoreExpenseRequest.
     *
     * `quote=lak` = `rate` ເປັນກີບ (ສິ່ງທີ່ໜ້າຈໍສົ່ງ). ບໍ່ສົ່ງ = "ຕໍ່ 1 USD" ແບບເດີມ.
     */
    public function check(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'rate' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'date' => ['required', 'date'],
            'quote' => ['nullable', 'in:usd,lak'],
        ]);

        $date = Carbon::parse($validated['date']);

        return response()->json(($validated['quote'] ?? 'usd') === 'lak'
            ? $this->fx->sanityCheckLakQuote($validated['currency'], $validated['rate'], $date)
            : $this->fx->sanityCheck($validated['currency'], $validated['rate'], $date));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ExchangeRate $rate = null): array
    {
        if (is_string($currency = $request->input('currency_code'))) {
            $request->merge(['currency_code' => strtoupper(trim($currency))]);
        }

        foreach (['rate_lak', 'rate_per_usd'] as $field) {
            if (is_string($value = $request->input($field))) {
                $request->merge([$field => str_replace([',', ' '], '', $value)]);
            }
        }

        $sometimes = $rate !== null ? ['sometimes'] : [];
        // ສ້າງໃໝ່ຕ້ອງມີອັດຕາອັນໃດອັນໜຶ່ງ. ແກ້ໄຂບໍ່ຕ້ອງ (ປ່ຽນແຕ່ແຫຼ່ງທີ່ມາກໍໄດ້)
        $oneRate = $rate === null ? ['required_without_all:rate_lak,rate_per_usd'] : [];

        return $request->validate([
            'currency_code' => [...$sometimes, 'required', 'string', 'size:3', 'exists:currencies,code',
                // USD ຄືຕົວຕັ້ງ (rate = 1) — ອັດຕາ USD ເປັນກີບຢູ່ໃນແຖວ LAK
                'not_in:USD'],
            'effective_date' => [...$sometimes, 'required', 'date',
                // ໜຶ່ງສະກຸນ ໜຶ່ງວັນ ໜຶ່ງ rate — ບໍ່ດັ່ງນັ້ນລາຍງານຈະບໍ່ແນ່ນອນ
                Rule::unique('exchange_rates', 'effective_date')
                    ->where('currency_code', $request->input('currency_code', $rate?->currency_code))
                    ->ignore($rate)],
            // ກີບຕໍ່ 1 ໜ່ວຍ (LAK: ກີບຕໍ່ 1 USD) — ສິ່ງທີ່ໜ້າຈໍສົ່ງ
            'rate_lak' => [...$oneRate, 'nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0', 'prohibits:rate_per_usd'],
            // ແບບເດີມ "ຈຳນວນສະກຸນຕໍ່ 1 USD" — ຍັງຮັບສຳລັບ API client ເກົ່າ
            'rate_per_usd' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0'],
            'source' => ['nullable', 'in:manual,bcel,bol,api'],
        ], [
            'currency_code.not_in' => 'USD ບໍ່ຕ້ອງຕັ້ງອັດຕາ — ອັດຕາ "1 USD = ? ກີບ" ໃຫ້ເລືອກ LAK',
            'effective_date.unique' => 'ມີອັດຕາຂອງສະກຸນນີ້ໃນວັນທີນີ້ຢູ່ແລ້ວ — ໃຫ້ແກ້ອັນເດີມແທນ',
            'rate_lak.required_without_all' => 'ກະລຸນາໃສ່ອັດຕາ',
            'rate_lak.regex' => 'ອັດຕາຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 22239 ຫຼື 675.48',
            'rate_per_usd.regex' => 'ອັດຕາຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 21760 ຫຼື 32.22',
        ]);
    }

    /**
     * ຄິດສອງຄໍລຳທີ່ເກັບຈາກສິ່ງທີ່ຄົນພິມ — ຜ່ານ CurrencyConverter ເທົ່ານັ້ນ.
     *
     *   LAK: rate_lak (ກີບຕໍ່ 1 USD) ຄືຄ່າ rate_per_usd ເລີຍ; rate_lak ເກັບ null
     *   THB: rate_lak ເກັບຕາມທີ່ພິມ; rate_per_usd = ອັດຕາ LAK ຂອງວັນນັ້ນ ÷ rate_lak
     *
     * ແກ້ວັນທີຂອງແຖວ THB ໂດຍບໍ່ແຕະອັດຕາ → ຄິດ rate_per_usd ຄືນດ້ວຍອັດຕາ LAK
     * ຂອງວັນໃໝ່ ບໍ່ດັ່ງນັ້ນສອງຄໍລຳຈະບໍ່ກົງກັນ.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException ເມື່ອຕັ້ງອັດຕາບາດ ແຕ່ຍັງບໍ່ມີອັດຕາ USD ໃຫ້ຄິດ
     */
    private function withStoredRates(array $validated, ?ExchangeRate $existing = null): array
    {
        $currency = strtoupper((string) ($validated['currency_code'] ?? $existing?->currency_code));
        $date = Carbon::parse($validated['effective_date'] ?? $existing?->effective_date);

        $rateLak = $validated['rate_lak'] ?? null;
        $ratePerUsd = $validated['rate_per_usd'] ?? null;
        unset($validated['rate_lak'], $validated['rate_per_usd']);

        // ບໍ່ໄດ້ສົ່ງອັດຕາມາ (ແກ້ແຕ່ວັນທີ/ແຫຼ່ງທີ່ມາ) → ໃຊ້ຄ່າທີ່ພິມໄວ້ເດີມ
        if ($rateLak === null && $ratePerUsd === null && $existing !== null) {
            if ($currency === 'LAK') {
                return $validated;
            }

            $rateLak = $existing->rate_lak;
            $ratePerUsd = $rateLak === null ? (string) $existing->rate_per_usd : null;
        }

        if ($currency === 'LAK') {
            return [...$validated, 'rate_per_usd' => $rateLak ?? $ratePerUsd, 'rate_lak' => null];
        }

        if ($rateLak === null) {
            // API ແບບເດີມ: ຮັບ "ຕໍ່ 1 USD" ຕາມທີ່ສົ່ງມາ. rate_lak = null →
            // rateFor() ໃຊ້ rate_per_usd ນີ້ໂດຍກົງຄືກ່ອນ — ບໍ່ຂຶ້ນກັບອັດຕາ LAK
            return [
                ...$validated,
                'rate_per_usd' => DecimalString::assert((string) $ratePerUsd, 'rate_per_usd'),
                'rate_lak' => null,
            ];
        }

        $rateLak = DecimalString::assert((string) $rateLak, 'rate_lak');

        try {
            $ratePerUsd = $this->fx->ratePerUsdFromLakQuote($currency, $rateLak, $date);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'rate_lak' => "ຍັງບໍ່ມີອັດຕາ USD (1 USD = ? ກີບ) ຂອງວັນທີ {$date->format('d/m/Y')} ຫຼື ກ່ອນໜ້າ — ຕັ້ງອັດຕາ USD ກ່ອນ ຈຶ່ງຈະຕັ້ງອັດຕາ {$currency} ເປັນກີບໄດ້",
            ]);
        }

        return [...$validated, 'rate_lak' => $rateLak, 'rate_per_usd' => $ratePerUsd];
    }
}

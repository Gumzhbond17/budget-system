<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Expense\InvalidStatusTransition;
use App\Domain\Money\CurrencyConverter;
use App\Domain\Money\DecimalString;
use App\Domain\Money\Money;
use App\Models\Currency;
use App\Models\Expense;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ExpenseService
{
    /**
     * ວິທີຈ່າຍທີ່ "ຜ່ານທະນາຄານ" ຈຶ່ງມີໃບໂອນ/ເຊັກໃຫ້ແນບ ແລະ ມີທະນາຄານໃຫ້ລະບຸ.
     * ເງິນສົດ/ຕິດໜີ້/ອື່ນໆ ບໍ່ມີ — ເບິ່ງ `bankIdFor()`.
     */
    private const BANK_METHODS = ['bank_transfer', 'mobile_banking', 'cheque'];

    public function __construct(
        private ExchangeRateService $fx,
        private DocumentNumberService $docNumbers,
        private CurrencyConverter $converter,
    ) {}

    /**
     * ສ້າງລາຍການລາຍຈ່າຍ ພ້ອມຄິດໄລ່ຍອດແປງທັງໝົດ.
     *
     * ນີ້ຄື **ຈຸດດຽວ** ໃນລະບົບທີ່ຄິດໄລ່ amount_usd_minor / amount_lak_minor
     * ຖ້າມີບ່ອນອື່ນຄິດເອງ = ຄວາມສ່ຽງ inconsistency (ບັນຫາທີ່ Excel ມີຢູ່)
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId): Expense {
            $currency = strtoupper((string) $data['currency_code']);
            $date = Carbon::parse($data['expense_date']);

            /** @var string|float|int $rawAmount */
            $rawAmount = $data['amount'];

            $expense = new Expense([
                ...$this->computeAmounts($currency, $rawAmount, $this->suppliedRate($data, $currency, $date), $date),
                'uuid' => (string) Str::uuid7(),
                'project_id' => $data['project_id'],
                'category_id' => $data['category_id'] ?? null,
                'payer_id' => $data['payer_id'] ?? null,
                'payee_id' => $data['payee_id'] ?? null,
                'bank_id' => $this->bankIdFor(
                    $data['bank_id'] ?? null,
                    (string) ($data['payment_method'] ?? 'bank_transfer'),
                ),
                'doc_no' => $this->docNumbers->nextFor((int) $data['project_id'], $date),
                'expense_date' => $date->toDateString(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'unit' => $data['unit'] ?? 'Lot',
                'quantity' => $data['quantity'] ?? 1,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'payment_reference' => $data['payment_reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? null,
                'status' => 'draft',
                'created_by' => $userId,
                'note' => $data['note'] ?? null,
            ]);

            $expense->save();

            return $expense;
        });
    }

    /**
     * ແກ້ໄຂລາຍການ ພ້ອມຄິດຍອດແປງຄືນໃໝ່ທັງໝົດ.
     *
     * ຄິດຄືນສະເໝີ ບໍ່ວ່າຈະແກ້ field ໃດ — ຖ້າຄິດຄືນສະເພາະບາງກໍລະນີ
     * ຈະມີທາງທີ່ amount_usd_minor ຄ້າງເປັນຄ່າເກົ່າໂດຍບໍ່ມີໃຜສັງເກດ.
     * `doc_no` ບໍ່ຖືກອອກໃໝ່ — ເລກທີ່ອອກໄປແລ້ວຄືເລກຖາວອນ.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        if ($expense->isLocked()) {
            throw InvalidStatusTransition::locked();
        }

        return DB::transaction(function () use ($expense, $data): Expense {
            $currency = strtoupper((string) ($data['currency_code'] ?? $expense->currency_code));
            $date = Carbon::parse($data['expense_date'] ?? $expense->expense_date);

            if ($currency !== $expense->currency_code && ! array_key_exists('amount', $data)) {
                throw new InvalidArgumentException(
                    'ປ່ຽນສະກຸນເງິນຕ້ອງສົ່ງ amount ມານຳ — minor unit ຂອງແຕ່ລະສະກຸນບໍ່ຄືກັນ'
                );
            }

            /** @var string|float|int $rawAmount */
            $rawAmount = $data['amount'] ?? $expense->amount_minor->toDecimal();

            // rate ເດີມຖືເປັນຄ່າຕັ້ງຕົ້ນເມື່ອສະກຸນບໍ່ປ່ຽນ — ບໍ່ດັ່ງນັ້ນ snapshot ຈະຖືກ
            // ຂຽນທັບດ້ວຍ rate ປັດຈຸບັນທຸກຄັ້ງທີ່ແກ້ຫົວຂໍ້ຊື່ໆ
            $suppliedRate = $this->suppliedRate($data, $currency, $date)
                ?? ($currency === $expense->currency_code
                    ? DecimalString::assert((string) $expense->fx_rate_per_usd, 'fx_rate_per_usd')
                    : null);

            $expense->fill([
                ...$this->computeAmounts($currency, $rawAmount, $suppliedRate, $date),
                ...array_intersect_key($data, array_flip([
                    'category_id', 'payer_id', 'payee_id', 'bank_id', 'title', 'description',
                    'unit', 'quantity', 'payment_method', 'payment_reference', 'paid_at', 'note',
                ])),
                'expense_date' => $date->toDateString(),
            ]);

            // ຄິດຫຼັງ fill ດ້ວຍຄ່າ**ຫຼັງແກ້**ຂອງທັງສອງຊ່ອງ — request ອາດສົ່ງມາ
            // ແຕ່ `payment_method: cash` ໂດຍບໍ່ແຕະ bank_id ເລີຍ ແລ້ວປະໃຫ້
            // ທະນາຄານເກົ່າຄ້າງຢູ່ກັບການຈ່າຍເປັນເງິນສົດ
            $expense->bank_id = $this->bankIdFor($expense->bank_id, $expense->payment_method);

            $expense->save();

            return $expense;
        });
    }

    /**
     * ທະນາຄານມີຄວາມໝາຍສະເພາະການຈ່າຍທີ່ຜ່ານທະນາຄານ.
     *
     * ບັງຄັບຢູ່ຈຸດນີ້ຈຸດດຽວ ບໍ່ແມ່ນຢູ່ຊັ້ນ validation: ການແກ້ໄຂແບບບາງສ່ວນ
     * (`sometimes`) ເຮັດໃຫ້ຄູ່ຄ່າທີ່ຂັດກັນເກີດຂຶ້ນໄດ້ໂດຍທີ່ request ດຽວນັ້ນ
     * ເບິ່ງຖືກຕ້ອງທຸກຢ່າງ — ມີແຕ່ບ່ອນທີ່ຮູ້ຄ່າສຸດທ້າຍທັງສອງຊ່ອງເທົ່ານັ້ນທີ່
     * ຕັດສິນໄດ້. ຄືນ null ແທນການໂຍນ error: ຜູ້ໃຊ້ປ່ຽນເປັນເງິນສົດ = ລາວ
     * ຕັ້ງໃຈເອົາທະນາຄານອອກ ບໍ່ແມ່ນພິມຜິດ.
     */
    private function bankIdFor(mixed $bankId, string $paymentMethod): ?int
    {
        if ($bankId === null || ! in_array($paymentMethod, self::BANK_METHODS, true)) {
            return null;
        }

        return (int) $bankId;
    }

    /** draft ຫຼື rejected → submitted */
    public function submit(Expense $expense): Expense
    {
        if (! in_array($expense->status, ['draft', 'rejected'], true)) {
            throw InvalidStatusTransition::from($expense->status, 'submitted');
        }

        $expense->forceFill([
            'status' => 'submitted',
            'rejection_reason' => null,
        ])->save();

        return $expense;
    }

    /**
     * submitted → approved. ຫຼັງຈາກນີ້ລາຍການ lock ຖາວອນ.
     *
     * ⚠️ ບໍ່ໄດ້ກວດວ່າ $approverId ຄືຜູ້ສ້າງເອງບໍ — ນັ້ນຄືໜ້າທີ່ຂອງ ExpensePolicy::approve()
     *    (segregation of duties). Service ຄຸມແຕ່ຄວາມຖືກຕ້ອງຂອງ state machine.
     */
    public function approve(Expense $expense, int $approverId): Expense
    {
        if ($expense->status !== 'submitted') {
            throw InvalidStatusTransition::from($expense->status, 'approved');
        }

        $expense->forceFill([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return $expense;
    }

    /** submitted → rejected (ສ້າງແກ້ແລ້ວ submit ຄືນໄດ້) */
    public function reject(Expense $expense, int $approverId, string $reason): Expense
    {
        if ($expense->status !== 'submitted') {
            throw InvalidStatusTransition::from($expense->status, 'rejected');
        }

        $expense->forceFill([
            'status' => 'rejected',
            'approved_by' => $approverId,   // ໃຜເປັນຄົນຕັດສິນ — ຕ້ອງຮູ້ເຖິງວ່າຈະປະຕິເສດ
            'approved_at' => null,
            'rejection_reason' => $reason,
        ])->save();

        return $expense;
    }

    /**
     * approved → rejected: ຜູ້ດູແລລະບົບສົ່ງກັບໃຫ້ຜູ້ບັນທຶກແກ້ (ເຊັ່ນ ອັດຕາແລກປ່ຽນຜິດ).
     *
     * ໃຊ້ສະຖານະ rejected ທີ່ມີຢູ່ແລ້ວ: ຜູ້ບັນທຶກແກ້ໄດ້ ແລະ ສົ່ງອະນຸມັດຄືນໄດ້ໂດຍ
     * ບໍ່ຕ້ອງມີເສັ້ນທາງໃໝ່. `approved_by` = ຄົນທີ່ສົ່ງກັບ (ຄືກັບ reject);
     * ການປ່ຽນແປງທັງໝົດຖືກບັນທຶກໃນ audit log ຜ່ານ LogsActivity.
     */
    public function reopen(Expense $expense, int $adminId, string $reason): Expense
    {
        if ($expense->status !== 'approved') {
            throw InvalidStatusTransition::from($expense->status, 'rejected');
        }

        $expense->forceFill([
            'status' => 'rejected',
            'approved_by' => $adminId,
            'approved_at' => null,
            'rejection_reason' => $reason,
        ])->save();

        return $expense;
    }

    /**
     * ຄິດ 6 ຄໍລຳເງິນທັງໝົດຈາກ input ດິບ — ຈຸດດຽວທີ່ຄິດ.
     *
     * @param  numeric-string|null  $suppliedRate  rate ທີ່ຜູ້ໃຊ້ປ້ອນ (ມີ priority ເໜືອ rate ໃນລະບົບ,
     *                                             ຍ້ອນຜູ້ໃຊ້ອາດແລກເງິນຈິງໃນອັດຕາທີ່ຕ່າງຈາກອັດຕາທາງການ)
     * @return array{
     *     currency_code: string,
     *     amount_minor: int,
     *     fx_rate_per_usd: numeric-string,
     *     fx_rate_lak_per_usd: numeric-string,
     *     amount_usd_minor: int,
     *     amount_lak_minor: int,
     * }
     */
    private function computeAmounts(
        string $currency,
        string|float|int $rawAmount,
        ?string $suppliedRate,
        CarbonInterface $date,
    ): array {
        $amount = Money::fromDecimal($rawAmount, $currency, Currency::decimalsFor($currency));
        $rate = $suppliedRate ?? $this->fx->rateFor($currency, $date);
        $lakRate = $currency === 'LAK' ? $rate : $this->fx->rateFor('LAK', $date);

        return [
            'currency_code' => $currency,
            'amount_minor' => $amount->minor,
            'fx_rate_per_usd' => $rate,
            'fx_rate_lak_per_usd' => $lakRate,
            'amount_usd_minor' => $this->converter->toUsdMinor($amount, $rate),
            'amount_lak_minor' => $this->converter->toLakMinor($amount, $rate, $lakRate),
        ];
    }

    /**
     * rate ທີ່ຜູ້ໃຊ້ປ້ອນ ເປັນ "ຕໍ່ 1 USD" ສະເໝີ.
     *
     * ຟອມສົ່ງ `fx_rate_lak` (ກີບຕໍ່ 1 USD / ກີບຕໍ່ 1 ບາດ) → ແປງຜ່ານ
     * CurrencyConverter ດ້ວຍອັດຕາ LAK ຂອງວັນທີລາຍການ.
     *
     * @param  array<string, mixed>  $data
     * @return numeric-string|null
     */
    private function suppliedRate(array $data, string $currency, CarbonInterface $date): ?string
    {
        if (isset($data['fx_rate_lak'])) {
            return $this->fx->ratePerUsdFromLakQuote(
                $currency,
                DecimalString::assert((string) $data['fx_rate_lak'], 'fx_rate_lak'),
                $date,
            );
        }

        if (! isset($data['fx_rate_per_usd'])) {
            return null;
        }

        return DecimalString::assert((string) $data['fx_rate_per_usd'], 'fx_rate_per_usd');
    }
}

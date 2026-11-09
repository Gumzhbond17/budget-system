<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Expense\InvalidStatusTransition;
use App\Domain\Money\CurrencyConverter;
use App\Domain\Money\DecimalString;
use App\Domain\Money\Money;
use App\Models\Currency;
use App\Models\Revenue;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ຄູ່ແຝດຂອງ ExpenseService ສຳລັບເງິນເຂົ້າ.
 *
 * ໃຊ້ `CurrencyConverter` ອັນດຽວກັນ — CLAUDE.md ບັງຄັບວ່າການແປງສະກຸນເກີດຂຶ້ນ
 * ບ່ອນດຽວ. ຖ້າຢູ່ນີ້ຄິດເອງ ຍອດ GL ຂອງລາຍຮັບກັບລາຍຈ່າຍຈະບໍ່ໃຊ້ກົດດຽວກັນ
 * ແລ້ວ "ຍອດຄົງເຫຼືອ" ຈະຜິດແບບງຽບໆ.
 */
final class RevenueService
{
    public function __construct(
        private ExchangeRateService $fx,
        private DocumentNumberService $docNumbers,
        private CurrencyConverter $converter,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): Revenue
    {
        return DB::transaction(function () use ($data, $userId): Revenue {
            $currency = strtoupper((string) $data['currency_code']);
            $date = Carbon::parse($data['received_date']);

            /** @var string|float|int $rawAmount */
            $rawAmount = $data['amount'];

            $revenue = new Revenue([
                ...$this->computeAmounts($currency, $rawAmount, $this->suppliedRate($data, $currency, $date), $date),
                'uuid' => (string) Str::uuid7(),
                'project_id' => $data['project_id'],
                'payer_id' => $data['payer_id'] ?? null,
                // ບໍ່ມີ `payment_method` ຢູ່ລາຍຮັບ ຈຶ່ງບໍ່ຕ້ອງກັ່ນຕອງຄືລາຍຈ່າຍ —
                // "ເງິນເຂົ້າບັນຊີໃດ" ເປັນຄຳຖາມທີ່ຖືກສະເໝີ
                'bank_id' => $data['bank_id'] ?? null,
                'doc_no' => $this->docNumbers->nextForRevenue((int) $data['project_id'], $date),
                'received_date' => $date->toDateString(),
                'title' => $data['title'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            $revenue->save();

            return $revenue;
        });
    }

    /**
     * ແກ້ໄຂ ພ້ອມຄິດຍອດແປງຄືນໃໝ່ທັງໝົດ — ເຫດຜົນດຽວກັນກັບ ExpenseService::update.
     * `doc_no` ບໍ່ຖືກອອກໃໝ່: ເລກທີ່ອອກໄປແລ້ວຄືເລກຖາວອນ.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Revenue $revenue, array $data): Revenue
    {
        if ($revenue->isLocked()) {
            throw InvalidStatusTransition::locked();
        }

        return DB::transaction(function () use ($revenue, $data): Revenue {
            $currency = strtoupper((string) ($data['currency_code'] ?? $revenue->currency_code));
            $date = Carbon::parse($data['received_date'] ?? $revenue->received_date);

            if ($currency !== $revenue->currency_code && ! array_key_exists('amount', $data)) {
                throw new InvalidArgumentException(
                    'ປ່ຽນສະກຸນເງິນຕ້ອງສົ່ງ amount ມານຳ — minor unit ຂອງແຕ່ລະສະກຸນບໍ່ຄືກັນ'
                );
            }

            /** @var string|float|int $rawAmount */
            $rawAmount = $data['amount'] ?? $revenue->amount_minor->toDecimal();

            // rate ເດີມຖືເປັນຄ່າຕັ້ງຕົ້ນເມື່ອສະກຸນບໍ່ປ່ຽນ — ບໍ່ດັ່ງນັ້ນ snapshot ຈະຖືກ
            // ຂຽນທັບດ້ວຍ rate ປັດຈຸບັນທຸກຄັ້ງທີ່ແກ້ຫົວຂໍ້ຊື່ໆ
            $suppliedRate = $this->suppliedRate($data, $currency, $date)
                ?? ($currency === $revenue->currency_code
                    ? DecimalString::assert((string) $revenue->fx_rate_per_usd, 'fx_rate_per_usd')
                    : null);

            $revenue->fill([
                ...$this->computeAmounts($currency, $rawAmount, $suppliedRate, $date),
                ...array_intersect_key($data, array_flip([
                    'payer_id', 'bank_id', 'title', 'reference', 'note',
                ])),
                'received_date' => $date->toDateString(),
            ]);

            $revenue->save();

            return $revenue;
        });
    }

    /** draft ຫຼື rejected → submitted */
    public function submit(Revenue $revenue): Revenue
    {
        if (! in_array($revenue->status, ['draft', 'rejected'], true)) {
            throw InvalidStatusTransition::from($revenue->status, 'submitted');
        }

        $revenue->forceFill([
            'status' => 'submitted',
            'rejection_reason' => null,
        ])->save();

        return $revenue;
    }

    /** submitted → approved. ຫຼັງຈາກນີ້ລາຍການ lock ຖາວອນ. */
    public function approve(Revenue $revenue, int $approverId): Revenue
    {
        if ($revenue->status !== 'submitted') {
            throw InvalidStatusTransition::from($revenue->status, 'approved');
        }

        $revenue->forceFill([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return $revenue;
    }

    /** submitted → rejected (ແກ້ແລ້ວ submit ຄືນໄດ້) */
    public function reject(Revenue $revenue, int $approverId, string $reason): Revenue
    {
        if ($revenue->status !== 'submitted') {
            throw InvalidStatusTransition::from($revenue->status, 'rejected');
        }

        $revenue->forceFill([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => null,
            'rejection_reason' => $reason,
        ])->save();

        return $revenue;
    }

    /** approved → rejected — ເບິ່ງ ExpenseService::reopen() */
    public function reopen(Revenue $revenue, int $adminId, string $reason): Revenue
    {
        if ($revenue->status !== 'approved') {
            throw InvalidStatusTransition::from($revenue->status, 'rejected');
        }

        $revenue->forceFill([
            'status' => 'rejected',
            'approved_by' => $adminId,
            'approved_at' => null,
            'rejection_reason' => $reason,
        ])->save();

        return $revenue;
    }

    /**
     * @param  numeric-string|null  $suppliedRate
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
     * ຄືກັບ ExpenseService::suppliedRate() — `fx_rate_lak` ຈາກຟອມ ແປງເປັນ
     * "ຕໍ່ 1 USD" ຜ່ານ CurrencyConverter.
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

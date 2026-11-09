<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money\CurrencyConverter;
use App\Domain\Money\DecimalString;
use App\Domain\Money\Money;
use App\Models\BudgetItem;
use App\Models\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ລາຍການງົບປະມານ (BOQ) ຈາກ sheet `ຕົ້ນທຶນ` — USD 1,493,145 / 11 ລາຍການ.
 *
 * ຈຸດດຽວທີ່ຄິດ `amount_minor` (= quantity × unit_price) ແລະ `amount_usd_minor`.
 * ໃນ Excel ເດີມຄົນຄິດເອງແລ້ວພິມໃສ່ — ນັ້ນຄືບ່ອນທີ່ຄວາມຜິດພາດເກີດ.
 */
final class BudgetItemService
{
    public function __construct(
        private ExchangeRateService $fx,
        private CurrencyConverter $converter,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BudgetItem
    {
        return DB::transaction(function () use ($data): BudgetItem {
            $item = new BudgetItem([
                ...$this->computeAmounts($data),
                'project_id' => $data['project_id'],
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'unit' => $data['unit'] ?? 'Lot',
                'quantity' => $data['quantity'] ?? 1,
                'sort_order' => $data['sort_order'] ?? 0,
                'note' => $data['note'] ?? null,
            ]);

            $item->save();

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BudgetItem $item, array $data): BudgetItem
    {
        return DB::transaction(function () use ($item, $data): BudgetItem {
            // ຄິດຄືນສະເໝີຈາກຄ່າປັດຈຸບັນ + ຄ່າໃໝ່ ບໍ່ດັ່ງນັ້ນ amount_usd_minor ຈະຄ້າງ
            $merged = [
                'currency_code' => $data['currency_code'] ?? $item->currency_code,
                'quantity' => $data['quantity'] ?? $item->quantity,
                'unit_price' => $data['unit_price'] ?? $item->unit_price_minor->toDecimal(),
                'fx_rate_per_usd' => $data['fx_rate_per_usd']
                    ?? (isset($data['currency_code']) ? null : (string) $item->fx_rate_per_usd),
                'rate_date' => $data['rate_date'] ?? null,
            ];

            $item->fill([
                ...$this->computeAmounts(array_filter($merged, fn ($v): bool => $v !== null)),
                ...array_intersect_key($data, array_flip(['category_id', 'name', 'unit', 'sort_order', 'note'])),
            ]);

            $item->save();

            return $item;
        });
    }

    /**
     * ຄິດ 5 ຄໍລຳເງິນທັງໝົດ.
     *
     * ງົບປະມານບໍ່ມີ "ວັນທີຈ່າຍ" ຈຶ່ງ snapshot rate ຂອງມື້ທີ່ບັນທຶກ (ຫຼື `rate_date`
     * ຖ້າສົ່ງມາ). USD ບໍ່ຕ້ອງມີ rate ໃນຕາຕະລາງເລີຍ — rateFor() ຄືນ 1 ໃຫ້ເອງ.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     currency_code: string,
     *     quantity: string,
     *     unit_price_minor: int,
     *     amount_minor: int,
     *     fx_rate_per_usd: numeric-string,
     *     amount_usd_minor: int,
     * }
     */
    private function computeAmounts(array $data): array
    {
        $currency = strtoupper((string) ($data['currency_code'] ?? 'USD'));
        $decimals = Currency::decimalsFor($currency);

        /** @var string|float|int $rawUnitPrice */
        $rawUnitPrice = $data['unit_price'];
        $unitPrice = Money::fromDecimal($rawUnitPrice, $currency, $decimals);

        $quantity = DecimalString::assert((string) ($data['quantity'] ?? 1), 'quantity');

        // ຄູນໃນ minor units ດ້ວຍ bcmath ແລ້ວປັດເສດຄັ້ງດຽວ — 2.5 × 50000 = 125000
        $amountMinor = (int) round((float) bcmul($quantity, (string) $unitPrice->minor, 4));
        $amount = new Money($amountMinor, $currency, $decimals);

        $rate = isset($data['fx_rate_per_usd'])
            ? DecimalString::assert((string) $data['fx_rate_per_usd'], 'fx_rate_per_usd')
            : $this->fx->rateFor($currency, Carbon::parse($data['rate_date'] ?? 'today'));

        return [
            'currency_code' => $currency,
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice->minor,
            'amount_minor' => $amountMinor,
            'fx_rate_per_usd' => $rate,
            'amount_usd_minor' => $this->converter->toUsdMinor($amount, $rate),
        ];
    }
}

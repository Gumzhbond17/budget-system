<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Domain\Money\CurrencyConverter;
use App\Domain\Money\DecimalString;
use App\Models\Expense;
use App\Models\Revenue;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ອັດຕາຂອງລາຍການເປັນກີບ ("1 USD = 22,239 ກີບ", "1 ບາດ = 675.48 ກີບ").
 *
 * ຄິດຈາກສອງ snapshot ທີ່ເກັບໄວ້ (`fx_rate_per_usd`, `fx_rate_lak_per_usd`)
 * ຜ່ານ CurrencyConverter — ບໍ່ໄດ້ເກັບເພີ່ມ. ຟອມແກ້ໄຂເອົາຄ່ານີ້ໄປໃສ່ຊ່ອງອັດຕາ
 * ແລະ ໜ້າລາຍລະອຽດສະແດງມັນ — Vue ຈຶ່ງບໍ່ຕ້ອງຫານເອງ.
 *
 * ໃຊ້ໄດ້ກັບ resource ຂອງ model ທີ່ມີ currency_code + ສອງຄໍລຳ rate (Expense, Revenue).
 *
 * @phpstan-require-extends JsonResource
 */
trait ExposesLakQuote
{
    /** @return numeric-string */
    protected function lakQuote(): string
    {
        /** @var Expense|Revenue $entry */
        $entry = $this->resource;

        return app(CurrencyConverter::class)->lakQuote(
            $entry->currency_code,
            DecimalString::assert((string) $entry->fx_rate_per_usd, 'fx_rate_per_usd'),
            DecimalString::assert((string) $entry->fx_rate_lak_per_usd, 'fx_rate_lak_per_usd'),
        );
    }
}

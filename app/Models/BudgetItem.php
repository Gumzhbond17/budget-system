<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Casts\MoneyMinor;
use App\Domain\Money\Money;
use App\Policies\BudgetItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ລາຍການງົບປະມານ (BOQ) ຈາກ sheet `ຕົ້ນທຶນ` — ໃຊ້ປຽບທຽບງົບ vs ຈິງ.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $category_id
 * @property string $name
 * @property string $currency_code
 * @property string $quantity
 * @property Money $unit_price_minor
 * @property Money $amount_minor
 * @property string $fx_rate_per_usd
 * @property int $amount_usd_minor
 */
#[Fillable([
    'project_id',
    'category_id',
    'name',
    'unit',
    'quantity',
    'currency_code',
    'unit_price_minor',
    'amount_minor',
    'fx_rate_per_usd',
    'amount_usd_minor',
    'sort_order',
    'note',
])]
#[UsePolicy(BudgetItemPolicy::class)]
class BudgetItem extends Model
{
    use LogsActivity;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_minor' => MoneyMinor::class.':currency_code',
            'amount_minor' => MoneyMinor::class.':currency_code',
            // ຍອດ USD ສະກຸນຄົງທີ່ຢູ່ແລ້ວ → integer ທຳມະດາ; rate ເປັນ string ສຳລັບ bcmath
            'amount_usd_minor' => 'integer',
            'fx_rate_per_usd' => 'decimal:10',
            'sort_order' => 'integer',
        ];
    }
}

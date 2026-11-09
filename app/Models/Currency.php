<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Policies\MasterDataPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * ສະກຸນເງິນ (ISO-4217). `decimal_places` ຄືຕົວກຳນົດວ່າ minor unit ຄູນດ້ວຍເທົ່າໃດ —
 * ຫ້າມ hardcode ໄວ້ໃນ code ບ່ອນອື່ນ (ເບິ່ງ docs/architecture.md ພາກ 2.1.ກ).
 *
 * @property string $code
 * @property string $name_lo
 * @property string $name_en
 * @property string $symbol
 * @property int $decimal_places
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name_lo', 'name_en', 'symbol', 'decimal_places', 'is_active', 'sort_order'])]
#[UsePolicy(MasterDataPolicy::class)]
#[WithoutIncrementing]
class Currency extends Model
{
    use LogsActivity;

    /**
     * ຕາຕະລາງທີ່ມີ FK ຊີ້ມາທີ່ `currencies.code` — ຄົບທັງ 5 ອັນ.
     *
     * ໃຊ້ຕັດສິນວ່າລຶບສະກຸນໄດ້ບໍ. ຂາດອັນໃດອັນໜຶ່ງ = ລຶບແລ້ວ FK ພັງເປັນ 500
     * ແທນທີ່ຈະເປັນ 409 ທີ່ອະທິບາຍໄດ້.
     *
     * @var list<string>
     */
    public const USAGE_RELATIONS = ['expenses', 'revenues', 'budgetItems', 'projects', 'exchangeRates'];

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'currency_code', 'code');
    }

    /** @return HasMany<Revenue, $this> */
    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class, 'currency_code', 'code');
    }

    /** @return HasMany<BudgetItem, $this> */
    public function budgetItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class, 'currency_code', 'code');
    }

    /** ໂຄງການທີ່ຕັ້ງງົບປະມານເປັນສະກຸນນີ້ */
    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'budget_currency_code', 'code');
    }

    /** @return HasMany<ExchangeRate, $this> */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_code', 'code');
    }

    /**
     * ຈຳນວນແຖວທັງໝົດທີ່ອ້າງເຖິງສະກຸນນີ້ — **ນັບແຖວທີ່ soft delete ແລ້ວນຳ**.
     *
     * ລາຍຈ່າຍທີ່ຖືກລຶບແບບ soft ຍັງຢູ່ໃນຕາຕະລາງ ແລະ FK ຍັງຊີ້ມາຢູ່ —
     * ຖ້ານັບແຕ່ແຖວທີ່ຍັງເຫັນ ຈະບອກຜູ້ໃຊ້ວ່າ "ລຶບໄດ້" ແລ້ວຄ່ອຍພັງຢູ່ຊັ້ນ DB.
     */
    public function usageCount(): int
    {
        $total = 0;

        foreach (self::USAGE_RELATIONS as $relation) {
            $total += $this->{$relation}()->withoutGlobalScopes()->count();
        }

        return $total;
    }

    /**
     * ຈຳນວນທົດສະນິຍົມຂອງສະກຸນ — cache ໄວ້ ເພາະ MoneyMinor cast ຖາມທຸກແຖວທີ່ອ່ານ.
     *
     * @throws InvalidArgumentException ຖ້າສະກຸນນັ້ນບໍ່ມີໃນຕາຕະລາງ
     */
    public static function decimalsFor(string $code): int
    {
        $code = strtoupper($code);

        return (int) Cache::rememberForever(self::decimalsCacheKey($code), function () use ($code): int {
            $decimals = static::query()->whereKey($code)->value('decimal_places');

            if ($decimals === null) {
                throw new InvalidArgumentException("Unknown currency code: {$code}");
            }

            return (int) $decimals;
        });
    }

    /** ລ້າງ cache ຫຼັງ seed / ແກ້ decimal_places */
    public static function flushDecimalsCache(): void
    {
        foreach (static::query()->pluck('code') as $code) {
            Cache::forget(self::decimalsCacheKey((string) $code));
        }
    }

    protected static function booted(): void
    {
        static::saved(function (self $currency): void {
            Cache::forget(self::decimalsCacheKey($currency->code));
        });

        static::deleted(function (self $currency): void {
            Cache::forget(self::decimalsCacheKey($currency->code));
        });
    }

    private static function decimalsCacheKey(string $code): string
    {
        return "currency:decimals:{$code}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

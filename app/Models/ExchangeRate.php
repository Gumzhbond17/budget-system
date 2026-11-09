<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Casts\DateOnly;
use App\Policies\MasterDataPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ອັດຕາແລກປ່ຽນອ້າງອີງ. `rate_per_usd` = ຈຳນວນ currency_code ຕໍ່ 1 USD ສະເໝີ
 * (docs/architecture.md ພາກ 2.1.ຂ) — ຫ້າມເກັບທິດກົງກັນຂ້າມ.
 *
 * ນີ້ຄື "ແຫຼ່ງອ້າງອີງ" ເທົ່ານັ້ນ. ຄ່າທີ່ໃຊ້ຈິງຖືກ snapshot ໄວ້ໃນ expenses.fx_rate_per_usd.
 *
 * ຄົນຕັ້ງອັດຕາເປັນກີບ (ໜ້າ "ອັດຕາແລກປ່ຽນ"):
 *   - ແຖວ LAK = "1 USD = X ກີບ" → ຢູ່ໃນ `rate_per_usd` ໂດຍກົງ, `rate_lak` = null
 *   - ແຖວອື່ນ (THB) = "1 ບາດ = X ກີບ" → `rate_lak`. ເມື່ອມີຄ່ານີ້
 *     ExchangeRateService::rateFor() ຄິດອັດຕາຕໍ່ USD ຈາກມັນ ÷ ອັດຕາ LAK ຂອງ
 *     **ວັນທີລາຍການ**; `rate_per_usd` ຂອງແຖວນັ້ນເປັນພຽງຄ່າຂອງວັນທີແຖວເອງ
 *     (ຕ້ອງມີເພາະຄໍລຳ NOT NULL) ແລະ ຈະເກົ່າລົງເມື່ອອັດຕາ USD ປ່ຽນ.
 *
 * @property int $id
 * @property string $currency_code
 * @property Carbon $effective_date
 * @property string $rate_per_usd
 * @property string|null $rate_lak
 * @property string $source
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['currency_code', 'effective_date', 'rate_per_usd', 'rate_lak', 'source', 'created_by'])]
#[UsePolicy(MasterDataPolicy::class)]
class ExchangeRate extends Model
{
    use LogsActivity;

    /**
     * ອັດຕາທີ່ **ມີຜົນ** ໃນວັນທີໜຶ່ງ: ຂອງວັນນັ້ນເອງກ່ອນ ຖ້າບໍ່ມີໃຫ້ຖອຍຫຼັງຫາອັນຫຼ້າສຸດ.
     *
     * ຫ້າມເອົາອັດຕາຂອງອະນາຄົດເດັດຂາດ — ຈະເຮັດໃຫ້ຍອດຂອງເດືອນທີ່ປິດບັນຊີໄປແລ້ວ
     * ຂະຍັບເມື່ອມີຄົນເພີ່ມອັດຕາຂອງມື້ໃໝ່. ກົດນີ້ຢູ່ບ່ອນດຽວ ເພື່ອບໍ່ໃຫ້ ExchangeRateService
     * ກັບ ExchangeRateController ຄິດຄົນລະແບບ.
     *
     * @param  Builder<ExchangeRate>  $query
     */
    #[Scope]
    protected function effectiveOn(Builder $query, string $currency, CarbonInterface $date): void
    {
        $query->where('currency_code', strtoupper($currency))
            ->where('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date');
    }

    /**
     * ໂທເຄັນທີ່ປ່ຽນທຸກເທື່ອທີ່ອັດຕາຂອງສະກຸນນີ້ຖືກແກ້ — ໃຊ້ເປັນສ່ວນໜຶ່ງຂອງ cache key.
     *
     * `rateFor()` cache ໄວ້ 6 ຊົ່ວໂມງ ຕໍ່ 1 ວັນທີ. ບໍ່ມີໂທເຄັນນີ້ ຄົນທີ່ຕັ້ງອັດຕາ
     * ຂອງມື້ນີ້ຫຼັງຈາກມີຄົນເປີດຟອມໄປແລ້ວ ຈະຍັງເຫັນອັດຕາເກົ່າອີກ 6 ຊົ່ວໂມງ —
     * ຊຶ່ງຄືສິ່ງທີ່ໜ້າ "ຕັ້ງອັດຕາປະຈຳວັນ" ຈະຊົນເຂົ້າທຸກມື້. ຕາຕະລາງ cache ເປັນ
     * database driver ຈຶ່ງໃຊ້ tag ບໍ່ໄດ້ — ປ່ຽນ key ແທນການລ້າງ.
     */
    public static function cacheToken(string $currency): string
    {
        return (string) Cache::get(self::tokenKey($currency), 'v0');
    }

    /**
     * ອັດຕາເປັນກີບຂອງແຖວນີ້ ແມ່ນ "ກີບຕໍ່ 1 ໜ່ວຍຂອງຫຍັງ".
     *
     * ແຖວ LAK ຄືຄູ່ USD-ກີບ ("1 USD = 22,239 ກີບ") — ໜ້າຈໍຈຶ່ງສະແດງເປັນ USD.
     */
    public static function quoteCodeFor(string $currency): string
    {
        $currency = strtoupper($currency);

        return $currency === 'LAK' ? 'USD' : $currency;
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ຊື່ຢູ່ໜ້າບັນທຶກການໃຊ້ງານ: "THB 2026-08-31" */
    public function activityLabel(): string
    {
        return $this->currency_code.' '.$this->effective_date->toDateString();
    }

    protected static function booted(): void
    {
        // ແກ້ວັນທີ/ສະກຸນ → ທັງອັນເກົ່າ ແລະ ອັນໃໝ່ ຕ້ອງລ້າງ ບໍ່ດັ່ງນັ້ນສະກຸນທີ່
        // ຍ້າຍອອກຈະຄ້າງອັດຕາທີ່ບໍ່ມີແຖວຮອງຮັບແລ້ວ
        $bump = function (self $rate): void {
            foreach (array_unique([$rate->currency_code, $rate->getOriginal('currency_code')]) as $code) {
                if (is_string($code)) {
                    // ⚠️ ຫ້າມໃຊ້ເວລາເປັນໂທເຄັນ (ເຄີຍເປັນ getTimestampMs()):
                    // ສອງການບັນທຶກທີ່ເກີດໃນ millisecond ດຽວກັນຈະໄດ້ໂທເຄັນຄືເກົ່າ
                    // → cache key ບໍ່ປ່ຽນ → ອັດຕາເກົ່າຖືກສົ່ງຕໍ່ອີກ 6 ຊົ່ວໂມງ.
                    // ເກີດຈິງເມື່ອ import ເປັນຊຸດ ຫຼື ແກ້ອັດຕາທີ່ຫາກໍສ້າງ.
                    // ໂທເຄັນຕ້ອງພຽງແຕ່ "ຕ່າງຈາກອັນເກົ່າ" ບໍ່ຕ້ອງຮຽງຕາມເວລາ.
                    Cache::forever(self::tokenKey($code), (string) Str::uuid7());
                }
            }
        };

        static::saved($bump);
        static::deleted($bump);
    }

    private static function tokenKey(string $currency): string
    {
        return 'fx:token:'.strtoupper($currency);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => DateOnly::class,
            // string ບໍ່ແມ່ນ float — ຄ່ານີ້ຖືກສົ່ງເຂົ້າ bcmath ໂດຍກົງ
            'rate_per_usd' => 'decimal:10',
            'rate_lak' => 'decimal:10',
        ];
    }
}

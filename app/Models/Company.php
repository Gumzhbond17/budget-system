<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Casts\DateOnly;
use App\Policies\CompanyPolicy;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ອົງກອນ — ຊັ້ນເທິງສຸດຂອງລະບົບ.
 *
 * ຂັ້ນຕອນການໃຊ້ງານ: ເລືອກອົງກອນ → ເລືອກໂຄງການຂອງອົງກອນນັ້ນ → ມອບສິດໃຫ້ຜູ້ໃຊ້.
 *
 * ຮອງຮັບຕົ້ນໄມ້ສາຂາ (ສຳນັກງານໃຫຍ່ → ສາຂາ → ຄູ່ຮ່ວມງານ) ດ້ວຍ materialized path.
 *
 * ⚠️ ຂໍ້ຕົກລົງເລື່ອງສິດ: ຕົ້ນໄມ້ນີ້ໃຊ້ **ຈັດກຸ່ມ ແລະ ລາຍງານ** ເທົ່ານັ້ນ —
 * ສິດເຂົ້າເຖິງ **ບໍ່ໄຫຼລົງ** ຕາມກິ່ງ. ຜູ້ໃຊ້ຢູ່ສຳນັກງານໃຫຍ່ຈະເຫັນຂໍ້ມູນສາຂາໄດ້
 * ກໍຕໍ່ເມື່ອຖືກເພີ່ມເຂົ້າໂຄງການນັ້ນຢ່າງຈະແຈ້ງ (ກັນຂໍ້ມູນຄູ່ຮ່ວມງານຮົ່ວຫາກັນ).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $parent_id
 * @property string $path
 * @property int $depth
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string $plan
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $access_expires_at
 * @property bool $is_active
 */
#[Fillable([
    'parent_id',
    'code',
    'name',
    'name_en',
    'type',
    'plan',
    'trial_ends_at',
    'access_expires_at',
    'tax_id',
    'phone',
    'email',
    'address',
    'is_active',
    'note',
    'created_by',
])]
#[UsePolicy(CompanyPolicy::class)]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    use LogsActivity;

    /**
     * internal    = ອົງກອນເຈົ້າຂອງລະບົບ
     * branch      = ສາຂາ/ໜ່ວຍງານພາຍໃນ
     * third_party = ອົງກອນພາຍນອກທີ່ມາໃຊ້ລະບົບ
     * store       = ຮ້ານ — ໜ່ວຍທີ່ບັນທຶກລາຍຮັບ-ລາຍຈ່າຍໄດ້ໂດຍກົງ
     */
    public const TYPES = ['internal', 'branch', 'third_party', 'store'];

    /**
     * ປະເພດທີ່ຜູ້ດູແລລະດັບອົງກອນສ້າງເອງໄດ້ພາຍໃຕ້ອົງກອນຕົນ.
     *
     * `internal` ກັບ `third_party` ບໍ່ຢູ່ໃນນີ້ — ສອງອັນນັ້ນຄືອົງກອນລະດັບເທິງສຸດ
     * ທີ່ມີແພັກເກັດ ແລະ ອາຍຸການໃຊ້ງານຂອງຕົນ ຈຶ່ງເປັນວຽກຂອງຜູ້ດູແລລະບົບ.
     */
    public const SELF_SERVICE_TYPES = ['branch', 'store'];

    public const PLANS = ['trial', 'active', 'suspended'];

    /** ຄວາມເລິກສູງສຸດຂອງຕົ້ນໄມ້ — ກັນ path ຍາວເກີນ 255 ຕົວອັກສອນ */
    public const MAX_DEPTH = 5;

    /** ຜົນຂອງ `billingRoot()` ທີ່ຄິດແລ້ວ — ກັນການຖາມ DB ຊ້ຳຕໍ່ແຖວ */
    private ?self $billingRoot = null;

    /**
     * ຄ່າເລີ່ມຕົ້ນຂອງ instance ໃໝ່ — ຕ້ອງກົງກັບ DEFAULT ຢູ່ migration.
     *
     * ⚠️ ຂາດອັນນີ້ບໍ່ໄດ້: DEFAULT ຂອງ DB ມີຜົນຕອນ INSERT ເທົ່ານັ້ນ ແລະ Eloquent
     * ບໍ່ໄດ້ອ່ານແຖວກັບຄືນມາຫຼັງສ້າງ. ດັ່ງນັ້ນ `Company::create(['code' => ..., 'name' => ...])`
     * ຈະໄດ້ object ທີ່ `is_active` = null ແລະ `plan` = null ໃນໜ່ວຍຄວາມຈຳ ເຮັດໃຫ້:
     *   - hasActiveAccess() ຄືນ false ໃຫ້ອົງກອນທີ່ຫາກໍສ້າງ (ຕອບ 201 ພ້ອມຂໍ້ມູນຜິດ)
     *   - hook `creating` ບໍ່ຕັ້ງ trial_ends_at ເພາະ plan ບໍ່ເທົ່າກັບ 'trial'
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'path' => '/',
        'depth' => 0,
        'type' => 'internal',
        'plan' => 'trial',
        'is_active' => true,
    ];

    /** @return BelongsTo<Company, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Company, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * ລູກຫຼານທຸກຊັ້ນ (ບໍ່ລວມຕົນເອງ) — LIKE ເທື່ອດຽວ ບໍ່ຕ້ອງ recursive CTE.
     *
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeDescendantsOf(Builder $query, self $company): Builder
    {
        return $query->where('path', 'like', $company->path.'%')
            ->whereKeyNot($company->getKey());
    }

    /**
     * ແຖວທີ່ຖືແພັກເກັດ ແລະ ອາຍຸການໃຊ້ງານຂອງກິ່ງນີ້ — ຮາກຂອງຕົ້ນໄມ້.
     *
     * ຂໍ້ຕົກລົງ: **ການເກັບເງິນຢູ່ລະດັບຮາກ** ສາຂາ ແລະ ຮ້ານບໍ່ມີແພັກເກັດຂອງຕົນ.
     * ບໍ່ດັ່ງນັ້ນຮ້ານທີ່ສ້າງໃໝ່ຈະໄດ້ trial 45 ວັນຂອງຕົນເອງ (ຈາກ hook `creating`)
     * ແລ້ວໝົດອາຍຸກ່ອນບໍລິສັດແມ່ທີ່ຈ່າຍເງິນແລ້ວ — ແລະ ການຕໍ່ອາຍຸຢູ່ບໍລິສັດແມ່
     * ຈະບໍ່ປົດລັອກໃຫ້ຮ້ານ.
     *
     * ⚠️ ນີ້ບໍ່ໄດ້ຂັດກັບກົດ "ສິດເຂົ້າເຖິງບໍ່ໄຫຼລົງຕາມກິ່ງ" — ອັນນັ້ນເວົ້າເຖິງ
     * ການ **ເຫັນຂໍ້ມູນການເງິນ** ເຊິ່ງຍັງຕ້ອງຜ່ານ `project_members` ຢູ່ຄືເກົ່າ.
     * ອັນນີ້ຄືເລື່ອງ **ການເກັບເງິນ** ເປັນຄົນລະແກນກັນ.
     */
    public function billingRoot(): self
    {
        // path = "/1/4/9/" → ຮາກຄື 1. ແຖວຮາກເອງມີ path = "/{id}/" ຈຶ່ງໄດ້ຕົນເອງ
        $segments = array_values(array_filter(explode('/', $this->path ?? '')));
        $rootId = $segments === [] ? null : (int) $segments[0];

        if ($rootId === null || $rootId === (int) $this->getKey()) {
            return $this;
        }

        // memoize: ລາຍການຜູ້ໃຊ້ຖາມອາຍຸຕໍ່ແຖວ ແລະ eager load ແບ່ງ instance
        // ດຽວກັນໃຫ້ທຸກຜູ້ໃຊ້ຂອງອົງກອນນັ້ນ — ຄິດເທື່ອດຽວພຽງພໍ
        return $this->billingRoot ??= self::query()->find($rootId) ?? $this;
    }

    /**
     * ວັນໝົດອາຍຸທີ່ມີຜົນຈິງ — ຂຶ້ນກັບ plan ຂອງແຖວທີ່ຖືການເກັບເງິນ.
     *
     * trial  → trial_ends_at
     * active → access_expires_at (null = ບໍ່ໝົດອາຍຸ)
     */
    public function effectiveExpiryDate(): ?Carbon
    {
        $billing = $this->billingRoot();

        return $billing->plan === 'trial'
            ? $billing->trial_ends_at
            : $billing->access_expires_at;
    }

    /** ແພັກເກັດທີ່ມີຜົນຈິງ — ຂອງຮາກ ບໍ່ແມ່ນຂອງແຖວນີ້ */
    public function effectivePlan(): ?string
    {
        return $this->billingRoot()->plan;
    }

    /**
     * ອົງກອນນີ້ຍັງເຂົ້າໃຊ້ລະບົບໄດ້ບໍ.
     *
     * ນີ້ຄືປະຕູດຽວທີ່ຕັດສິນເລື່ອງອາຍຸ — middleware, policy ແລະ API resource
     * ຕ້ອງຖາມຜ່ານນີ້ ຫ້າມປຽບທຽບວັນທີເອງ.
     *
     * `is_active` ຖືກກວດຂອງ **ແຖວນີ້** ບໍ່ແມ່ນຂອງຮາກ — ປິດຮ້ານໜຶ່ງຮ້ານໄດ້
     * ໂດຍບໍ່ຕ້ອງປິດທັງບໍລິສັດ.
     */
    public function hasActiveAccess(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $billing = $this->billingRoot();

        if (! $billing->is_active || $billing->plan === 'suspended') {
            return false;
        }

        $expiry = $this->effectiveExpiryDate();

        // ບໍ່ໄດ້ກຳນົດວັນໝົດອາຍຸ = ໃຊ້ໄດ້ຕະຫຼອດ
        return $expiry === null || $expiry->gte(Carbon::today());
    }

    /** ເຫຼືອອີກຈັກວັນຈຶ່ງໝົດອາຍຸ — ຕິດລົບ = ໝົດໄປແລ້ວ, null = ບໍ່ໝົດອາຍຸ */
    public function expiresInDays(): ?int
    {
        $expiry = $this->effectiveExpiryDate();

        return $expiry === null
            ? null
            : (int) Carbon::today()->diffInDays($expiry, false);
    }

    protected static function booted(): void
    {
        static::creating(function (self $company): void {
            $company->uuid ??= (string) Str::uuid7();

            // trial ທີ່ບໍ່ໄດ້ກຳນົດວັນສິ້ນສຸດ → ນັບຈາກມື້ນີ້ໄປ config('company.trial_days')
            if ($company->plan === 'trial' && $company->trial_ends_at === null) {
                $company->trial_ends_at = Carbon::today()
                    ->addDays((int) config('company.trial_days', 45));
            }

            $company->syncTreePosition();
        });

        // path ຕ້ອງມີ id ຂອງຕົນເອງ ຈຶ່ງຂຽນໄດ້ຫຼັງ insert ເທົ່ານັ້ນ
        static::created(function (self $company): void {
            $path = $company->buildPath();

            $company->newQuery()->whereKey($company->getKey())->update(['path' => $path]);

            $company->setAttribute('path', $path)->syncOriginalAttribute('path');
        });

        static::updating(function (self $company): void {
            if ($company->isDirty('parent_id')) {
                $company->syncTreePosition();
            }
        });

        // ຍ້າຍກິ່ງ → ຂຽນ path ຂອງລູກຫຼານທັງໝົດຄືນ
        static::updated(function (self $company): void {
            if ($company->wasChanged('parent_id')) {
                $company->rewriteDescendantPaths();
            }
        });
    }

    /** ຄິດ depth ຄືນຈາກ parent ປັດຈຸບັນ */
    private function syncTreePosition(): void
    {
        $parent = $this->parent_id !== null
            ? self::query()->find($this->parent_id)
            : null;

        $this->depth = $parent !== null ? $parent->depth + 1 : 0;
    }

    private function buildPath(): string
    {
        $parent = $this->parent_id !== null
            ? self::query()->find($this->parent_id)
            : null;

        $prefix = $parent !== null ? $parent->path : '/';

        return $prefix.$this->getKey().'/';
    }

    private function rewriteDescendantPaths(): void
    {
        $newPath = $this->buildPath();
        $oldPath = $this->getOriginal('path') ?? $newPath;

        if ($newPath === $oldPath) {
            return;
        }

        self::query()
            ->where('path', 'like', $oldPath.'%')
            ->whereKeyNot($this->getKey())
            ->get()
            ->each(function (self $descendant) use ($oldPath, $newPath): void {
                $descendant->newQuery()->whereKey($descendant->getKey())->update([
                    'path' => $newPath.Str::after($descendant->path, $oldPath),
                    'depth' => $this->depth + substr_count(Str::after($descendant->path, $oldPath), '/'),
                ]);
            });

        $this->newQuery()->whereKey($this->getKey())->update(['path' => $newPath]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => DateOnly::class,
            'access_expires_at' => DateOnly::class,
            'is_active' => 'boolean',
            'depth' => 'integer',
        ];
    }
}

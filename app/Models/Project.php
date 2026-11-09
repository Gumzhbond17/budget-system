<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Casts\DateOnly;
use App\Policies\ProjectPolicy;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * ໂຄງການ — sheet `Sheet1` ມີ 3 ໂຄງການ (Solar, ໄຊສົມບູນ, ...) ຈຶ່ງຕ້ອງແຍກຕັ້ງແຕ່ຕົ້ນ.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 * @property string $budget_currency_code
 * @property int $budget_amount_minor
 * @property string $status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 */
#[Fillable([
    'uuid',
    'company_id',
    'code',
    'name',
    'description',
    'location',
    'capacity_kw',
    'start_date',
    'end_date',
    'budget_currency_code',
    'budget_amount_minor',
    'status',
    'created_by',
])]
#[UsePolicy(ProjectPolicy::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    use LogsActivity;

    /**
     * ຄ່າເລີ່ມຕົ້ນຂອງ instance ໃໝ່ — ຕ້ອງກົງກັບ DEFAULT ຢູ່ migration.
     *
     * ⚠️ DEFAULT ຂອງ DB ມີຜົນຕອນ INSERT ເທົ່ານັ້ນ ແລະ Eloquent ບໍ່ໄດ້ອ່ານແຖວ
     * ກັບຄືນມາຫຼັງສ້າງ — ດັ່ງນັ້ນໂຄງການທີ່ຫາກໍສ້າງໂດຍບໍ່ໄດ້ລະບຸສະກຸນເງິນ
     * ຈະມີ `budget_currency_code` = null ໃນໜ່ວຍຄວາມຈຳ ແລ້ວ ProjectResource
     * ຈະລົ້ມທັນທີຕອນ format ຈຳນວນເງິນ (Currency::decimalsFor(null)).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'budget_currency_code' => 'USD',
        'budget_amount_minor' => 0,
        'status' => 'planning',
    ];

    /**
     * ອົງກອນເຈົ້າຂອງໂຄງການ — ບັງຄັບ (NOT NULL) ຕັ້ງແຕ່ລະດັບ DB.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<BudgetItem, $this> */
    public function budgetItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    /** @return HasMany<Revenue, $this> */
    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    /** @return HasMany<ProjectMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return BelongsTo<Currency, $this> */
    public function budgetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'budget_currency_code', 'code');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'capacity_kw' => 'integer',
            'budget_amount_minor' => 'integer',
        ];
    }
}

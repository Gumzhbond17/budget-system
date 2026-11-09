<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Policies\MasterDataPolicy;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ໝວດລາຍຈ່າຍ — ຮອງຮັບແມ່-ລູກ ເພາະ sheet ເດີມມີຫົວຂໍ້ໃຫຍ່ ("I. ການເຮັດເອກະສານ…")
 * ຄຸມໝວດຍ່ອຍຢູ່ຂ້າງລຸ່ມ.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name_lo
 * @property string|null $name_en
 * @property string $color
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['parent_id', 'code', 'name_lo', 'name_en', 'color', 'icon', 'is_active', 'sort_order'])]
#[UsePolicy(MasterDataPolicy::class)]
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory;

    use LogsActivity;

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ExpenseCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

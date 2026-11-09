<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Policies\MasterDataPolicy;
use Database\Factories\PayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ຜູ້ຈ່າຍ / ແຫຼ່ງທຶນ (Sinexay, SSS, Sengsamai, phanh).
 *
 * ເປັນຕາຕະລາງແທນ string ເພື່ອກັນ "SSS" vs "SSS " vs "Sengsamai/Saengsamai"
 * ທີ່ພົບໃນ Excel ເດີມ — docs/architecture.md ພາກ 1.4.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property bool $is_active
 */
#[Fillable(['name', 'type', 'contact_phone', 'note', 'is_active'])]
#[UsePolicy(MasterDataPolicy::class)]
class Payer extends Model
{
    /** @use HasFactory<PayerFactory> */
    use HasFactory;

    use LogsActivity;

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** @return HasMany<Revenue, $this> */
    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

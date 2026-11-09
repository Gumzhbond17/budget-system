<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Policies\MasterDataPolicy;
use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ທະນາຄານ — ຂໍ້ມູນຫຼັກທີ່ໃຊ້ຮ່ວມກັນທຸກໂຄງການ.
 *
 * ⚠️ `logo_path` ຖືກ #[Hidden] ຄືກັບ Attachment::$path: ທາງດຽວທີ່ຈະເຫັນ
 * ໄຟລ໌ຄື signed URL ທີ່ BankResource ສ້າງໃຫ້ — ບໍ່ແມ່ນຈາກການເດົາ path.
 *
 * ຄໍລຳໂລໂກ້ທັງໝົດຖືກຕັ້ງໂດຍ BankLogoService ເທົ່ານັ້ນ ຈຶ່ງບໍ່ຢູ່ໃນ Fillable —
 * ຟອມສົ່ງ `logo_path` ມາເອງບໍ່ໄດ້.
 *
 * @property int $id
 * @property string $code
 * @property string $name_lo
 * @property string|null $name_en
 * @property string|null $logo_path
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $logo_updated_at
 */
#[Fillable(['code', 'name_lo', 'name_en', 'short_name', 'swift_code', 'note', 'is_active', 'sort_order'])]
#[Hidden(['logo_path'])]
#[UsePolicy(MasterDataPolicy::class)]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
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
     * ຈຳນວນລາຍການທີ່ອ້າງເຖິງທະນາຄານນີ້ — ໃຊ້ຕັດສິນວ່າລຶບໄດ້ບໍ.
     *
     * ນັບແຖວທີ່ soft-delete ໄປແລ້ວນຳ: FK ຍັງຊີ້ມາຢູ່ ແລະ ລາຍການເຫຼົ່ານັ້ນ
     * ກູ້ຄືນໄດ້ — ລຶບທະນາຄານຖິ້ມແລ້ວກູ້ຄືນມາຈະບໍ່ຮູ້ວ່າໂອນຜ່ານທະນາຄານໃດ.
     */
    public function usageCount(): int
    {
        return $this->expenses()->withoutGlobalScopes()->withTrashed()->count()
            + $this->revenues()->withoutGlobalScopes()->withTrashed()->count();
    }

    public function hasLogo(): bool
    {
        return $this->logo_path !== null;
    }

    /** ຊື່ທີ່ເອົາໄປສະແດງເມື່ອບໍ່ຮູ້ພາສາ — ຫຍໍ້ກ່ອນ ຖ້າບໍ່ມີຈຶ່ງໃຊ້ຊື່ເຕັມ */
    public function displayName(): string
    {
        return $this->short_name ?? $this->name_lo;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'logo_updated_at' => 'datetime',
        ];
    }
}

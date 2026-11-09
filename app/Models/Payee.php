<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Policies\MasterDataPolicy;
use Database\Factories\PayeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ຜູ້ຮັບເງິນ (ຮ້ານຄ້າ/ບຸກຄົນ/ລັດ).
 *
 * `bank_account_no` ເຂົ້າລະຫັດຢູ່ຊັ້ນ application (ພາກ 9) ແລະ ຖືກ hide ຈາກ
 * array/JSON — API ຄວນສະແດງຜ່ານ `masked_bank_account_no` ເທົ່ານັ້ນ.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string|null $bank_account_no
 * @property bool $is_active
 */
#[Fillable(['name', 'type', 'phone', 'bank_name', 'bank_account_no', 'tax_id', 'note', 'is_active'])]
#[Hidden(['bank_account_no'])]
#[UsePolicy(MasterDataPolicy::class)]
class Payee extends Model
{
    /** @use HasFactory<PayeeFactory> */
    use HasFactory;

    use LogsActivity;

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * ສະແດງແຕ່ 4 ຕົວທ້າຍ — ພາກ 9 "mask ໃນ API response".
     *
     * ເປັນ method ບໍ່ແມ່ນ accessor ໂດຍເຈດຕະນາ: ຄ່າທີ່ mask ແລ້ວຕ້ອງຖືກ
     * ເອີ້ນອອກມາຢ່າງຕັ້ງໃຈຈາກ API Resource — ບໍ່ແມ່ນຫຼຸດອອກ toArray() ເອງ.
     */
    public function maskedBankAccountNo(): ?string
    {
        $account = $this->bank_account_no;

        if ($account === null || $account === '') {
            return null;
        }

        return str_repeat('•', max(mb_strlen($account) - 4, 0)).mb_substr($account, -4);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // ⚠️ ເຂົ້າລະຫັດແລ້ວຄົ້ນຫາ/index ດ້ວຍເລກບັນຊີບໍ່ໄດ້ອີກ — ນັ້ນຄືລາຄາຂອງຄວາມປອດໄພ
            'bank_account_no' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}

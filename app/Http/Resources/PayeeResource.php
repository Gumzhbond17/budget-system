<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payee
 */
final class PayeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'phone' => $this->phone,
            'bank_name' => $this->bank_name,
            // ⚠️ ສະແດງແຕ່ 4 ຕົວທ້າຍ — ເລກເຕັມບໍ່ເຄີຍອອກຈາກ server (ພາກ 9)
            'bank_account_no_masked' => $this->maskedBankAccountNo(),
            'tax_id' => $this->tax_id,
            'note' => $this->note,
            'is_active' => $this->is_active,

            // ໜ້າຈັດການໃຊ້ຕັດສິນວ່າປຸ່ມລຶບກົດໄດ້ບໍ (ເບິ່ງ PayeeController::destroy)
            'expenses_count' => $this->whenCounted('expenses'),
        ];
    }
}

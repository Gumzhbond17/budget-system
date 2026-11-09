<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payer
 */
final class PayerResource extends JsonResource
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
            'contact_phone' => $this->contact_phone,
            'note' => $this->note,
            'is_active' => $this->is_active,

            // ໜ້າຈັດການໃຊ້ຕັດສິນວ່າປຸ່ມລຶບກົດໄດ້ບໍ — ບໍ່ດັ່ງນັ້ນຜູ້ໃຊ້ຈະກົດ
            // ແລ້ວຄ່ອຍຮູ້ວ່າລຶບບໍ່ໄດ້ຈາກ 409
            'expenses_count' => $this->whenCounted('expenses'),
            'revenues_count' => $this->whenCounted('revenues'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenseCategory
 */
final class ExpenseCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name_lo' => $this->name_lo,
            'name_en' => $this->name_en,
            // ສີຂອງ chart ມາຈາກ DB ບໍ່ແມ່ນ hardcode ໃນ Vue (ພາກ 2.3)
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'children' => self::collection($this->whenLoaded('children')),

            // ໜ້າຈັດການໝວດໃຊ້ຕັດສິນວ່າປຸ່ມລຶບກົດໄດ້ບໍ — ບໍ່ດັ່ງນັ້ນຜູ້ໃຊ້ຈະກົດ
            // ແລ້ວຄ່ອຍຮູ້ວ່າລຶບບໍ່ໄດ້ຈາກ 409
            'expenses_count' => $this->whenCounted('expenses'),
            'children_count' => $this->whenCounted('children'),
        ];
    }
}

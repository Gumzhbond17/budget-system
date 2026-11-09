<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Currency;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
final class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'capacity_kw' => $this->capacity_kw,
            'status' => $this->status,

            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),

            'budget' => MoneyResource::fromMinor(
                $this->budget_amount_minor,
                $this->budget_currency_code,
                Currency::decimalsFor($this->budget_currency_code),
            ),

            'company' => CompanyResource::make($this->whenLoaded('company')),

            'members_count' => $this->whenCounted('members'),
            'expenses_count' => $this->whenCounted('expenses'),

            // role ຂອງຜູ້ໃຊ້ທີ່ຮ້ອງຂໍ ໃນໂຄງການນີ້ — SPA ໃຊ້ຕັດສິນວ່າຈະສະແດງ
            // ປຸ່ມ "ເພີ່ມລາຍຈ່າຍ" ຫຼື "ອະນຸມັດ" ບໍ. ບໍ່ແມ່ນການບັງຄັບສິດ —
            // ການບັງຄັບຈິງຢູ່ Policy ຝັ່ງ server ສະເໝີ.
            'my_role' => $request->user()?->projectRoles()[$this->id] ?? null,
        ];
    }
}

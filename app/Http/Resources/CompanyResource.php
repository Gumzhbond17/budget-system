<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
final class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // id ພາຍໃນບໍ່ຖືກສົ່ງອອກ — SPA ອ້າງອີງດ້ວຍ uuid ສະເໝີ (ກັນ enumeration)
            'uuid' => $this->uuid,
            'parent_uuid' => $this->whenLoaded('parent', fn () => $this->parent?->uuid),
            'depth' => $this->depth,

            'code' => $this->code,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'type' => $this->type,

            // ---------- ອາຍຸການໃຊ້ງານ ----------
            // ແພັກເກັດທີ່ **ມີຜົນຈິງ** — ຂອງຮາກຕົ້ນໄມ້ ບໍ່ແມ່ນຄ່າດິບຂອງແຖວນີ້.
            // ສາຂາ/ຮ້ານບໍ່ມີແພັກເກັດຂອງຕົນ (ເບິ່ງ Company::billingRoot) ຖ້າສົ່ງ
            // ຄ່າດິບ ຮ້ານທີ່ຫາກໍສ້າງຈະຂຶ້ນປ້າຍ "ທົດລອງ · ເຫຼືອ 45 ວັນ" ທັງທີ່
            // ບໍລິສັດແມ່ຈ່າຍເງິນຮອດປີໜ້າແລ້ວ
            'plan' => $this->effectivePlan(),
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'access_expires_at' => $this->access_expires_at?->toDateString(),

            // ອັນທີ່ມີຜົນຈິງຕາມ plan — ສົ່ງສຳເລັດຮູບເພື່ອບໍ່ໃຫ້ SPA ໄປຂຽນກົດ
            // "trial ໃຊ້ trial_ends_at, ບໍ່ດັ່ງນັ້ນໃຊ້ access_expires_at" ຊ້ຳອີກບ່ອນ
            'effective_expires_at' => $this->effectiveExpiryDate()?->toDateString(),

            // ຄິດຢູ່ຝັ່ງ server ບ່ອນດຽວ — SPA ຫ້າມປຽບທຽບວັນທີເອງ ເພາະ
            // timezone ຂອງ browser ອາດຕ່າງຈາກ server ແລ້ວຂີດເສັ້ນຄົນລະມື້
            'has_active_access' => $this->hasActiveAccess(),
            'expires_in_days' => $this->expiresInDays(),

            'tax_id' => $this->tax_id,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'note' => $this->note,

            // ສິດຕໍ່ແຖວນີ້ — Policy ເປັນຜູ້ຕອບ ບໍ່ແມ່ນ UI ຄິດເອງ (ເຫດຜົນດຽວກັນ
            // ກັບ ExposesWorkflowPermissions). ຢູ່ນີ້ຈຳເປັນກວ່າເກົ່າອີກ ເພາະ
            // ກົດແມ່ນ "ສາຂາ/ຮ້ານ ທີ່ຢູ່ໃນຕົ້ນໄມ້ຂອງຕົນ" ເຊິ່ງຕ້ອງທຽບ `path`
            // ກັບອົງກອນຂອງຜູ້ໃຊ້ — SPA ບໍ່ມີຂໍ້ມູນນັ້ນຢູ່ໃນມື.
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],

            // counter ສຳລັບໜ້າລາຍການ — ໂຫຼດດ້ວຍ withCount() ຫ້າມ N+1
            'projects_count' => $this->whenCounted('projects'),
            'users_count' => $this->whenCounted('users'),
            'children_count' => $this->whenCounted('children'),

            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // signed 24 ຊົ່ວໂມງ ຄືກັບໂລໂກ້ທະນາຄານ: ຮູບບໍ່ແມ່ນຄວາມລັບ ແລະ ຮູບໃນ
            // header ບໍ່ຄວນແຕກຢູ່ແທັບທີ່ເປີດຄ້າງໄວ້ໝົດມື້. path ຈິງບໍ່ເຄີຍອອກໄປ
            'avatar_url' => $this->hasAvatar()
                ? URL::temporarySignedRoute(
                    'users.avatar',
                    now()->addDay(),
                    // `v` = cache-buster: ປ່ຽນຮູບແລ້ວ URL ຕ້ອງປ່ຽນ ເຖິງແມ່ນ expiry ຈະຕົກວິນາທີດຽວກັນ
                    ['user' => $this->id, 'v' => $this->avatar_updated_at?->timestamp],
                )
                : null,
            'is_platform_admin' => $this->is_platform_admin,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toDateString(),

            'company' => CompanyResource::make($this->whenLoaded('company')),

            // ສະທ້ອນ MasterDataPolicy::create ໂດຍກົງ — SPA ບໍ່ມີ memberships
            // ຢູ່ໃນມືຕອນບູດ ຈຶ່ງເດົາເອງບໍ່ໄດ້ວ່າຈະສະແດງປຸ່ມ "ເພີ່ມໝວດ" ບໍ.
            // ໝາຍເຫດ: is_platform_admin ບໍ່ແມ່ນທາງລັດ — policy ບໍ່ຍົກເວັ້ນໃຫ້.
            'can_manage_master_data' => $this->hasAnyProjectRole(['full_access', 'modify']),

            // ອາຍຸມາຈາກສອງແຫຼ່ງ: ອົງກອນ ແລະ ບັນຊີເອງ ເຊິ່ງຕ້ອງຜ່ານ **ທັງສອງ**.
            // ສະຫຼຸບໄວ້ນີ້ເພື່ອໃຫ້ຕາຕະລາງສະແດງ "ໝົດອາຍຸໃນ 12 ວັນ" ໄດ້ໂດຍບໍ່ຕ້ອງ
            // ໃຫ້ SPA ໄປປະກອບເອງ — ແລະ ຫ້າມ SPA ປຽບທຽບວັນທີເອງ (timezone ຕ່າງກັນ).
            //
            // expires_at   = ອັນທີ່ມາຮອດກ່ອນລະຫວ່າງສອງແຫຼ່ງ (ອັນທີ່ມີຜົນຈິງ)
            // user/company = ແຍກໃຫ້ເຫັນວ່າອັນໃດເປັນຕົວບັງຄັບ ເພື່ອໃຫ້ຟອມແກ້ໄຂ
            //                ເຕືອນໄດ້ວ່າ "ຕັ້ງວັນນີ້ໄປກໍບໍ່ມີຜົນ ອົງກອນໝົດກ່ອນ"
            'access' => [
                'can_access' => $this->canAccessSystem(),
                'plan' => $this->company?->plan,
                'expires_at' => $this->effectiveExpiryDate()?->toDateString(),
                'expires_in_days' => $this->expiresInDays(),
                'user_expires_at' => $this->access_expires_at?->toDateString(),
                'company_expires_at' => $this->company?->effectiveExpiryDate()?->toDateString(),
            ],

            // ສິດຕໍ່ໂຄງການ — ໂຫຼດເມື່ອຮ້ອງຂໍລາຍລະອຽດຜູ້ໃຊ້ຄົນດຽວ
            'memberships' => ProjectMemberResource::collection(
                $this->whenLoaded('projectMemberships')
            ),
        ];
    }
}

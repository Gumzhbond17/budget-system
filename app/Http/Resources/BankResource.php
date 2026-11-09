<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Bank
 *
 * ⚠️ ບໍ່ມີ `logo_path` ຢູ່ນີ້ (Model ກໍ #[Hidden] ໄວ້ແລ້ວ) — ທາງດຽວທີ່ຈະເຫັນ
 * ໄຟລ໌ຄື signed URL ຂ້າງລຸ່ມ.
 */
final class BankResource extends JsonResource
{
    /**
     * ອາຍຸ URL ໂລໂກ້ — 24 ຊົ່ວໂມງ, ດົນກວ່າຫຼັກຖານ (5–30 ນາທີ) ຫຼາຍ.
     *
     * ເຫດຜົນ: ໂລໂກ້ທະນາຄານຄືເຄື່ອງໝາຍການຄ້າທີ່ເປີດເຜີຍຢູ່ແລ້ວ — URL ທີ່ຮົ່ວ
     * ບໍ່ໄດ້ເປີດເຜີຍຫຍັງ ຕ່າງຈາກໃບໂອນເງິນທີ່ມີເລກບັນຊີ. ອາຍຸສັ້ນຈຶ່ງມີແຕ່ຜົນ
     * ເສຍ: ໜ້າຈັດການທີ່ເປີດຄ້າງໄວ້ດົນຈະກາຍເປັນຮູບແຕກທັງໜ້າ.
     */
    private const LOGO_TTL = 86400;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_lo' => $this->name_lo,
            'name_en' => $this->name_en,
            'short_name' => $this->short_name,
            'swift_code' => $this->swift_code,
            'note' => $this->note,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            // ໜ້າຈັດການໃຊ້ຕັດສິນວ່າປຸ່ມລຶບກົດໄດ້ບໍ (ເບິ່ງ BankController::destroy).
            // ມີສະເພາະເມື່ອຂໍ `with_usage`
            'expenses_count' => $this->whenCounted('expenses'),
            'revenues_count' => $this->whenCounted('revenues'),

            // ບອກແຍກຈາກ `logo_url`: ໜ້າຈໍຕ້ອງຮູ້ວ່າ "ບໍ່ມີໂລໂກ້" ຕ່າງຈາກ
            // "ມີແຕ່ URL ໝົດອາຍຸແລ້ວ" ເພື່ອສະແດງປຸ່ມລຶບໃຫ້ຖືກ
            'has_logo' => $this->hasLogo(),
            'logo_url' => $this->hasLogo()
                ? URL::temporarySignedRoute(
                    'banks.logo',
                    now()->addSeconds(self::LOGO_TTL),
                    ['bank' => $this->id],
                )
                : null,
            'logo_updated_at' => $this->logo_updated_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExposesLakQuote;
use App\Http\Resources\Concerns\ExposesWorkflowPermissions;
use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Revenue
 */
final class RevenueResource extends JsonResource
{
    use ExposesLakQuote;
    use ExposesWorkflowPermissions;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'doc_no' => $this->doc_no,
            'received_date' => $this->received_date->toDateString(),
            'title' => $this->title,

            // ---------- ຈຳນວນເງິນ ----------
            'amount' => MoneyResource::make($this->amount_minor),
            'amount_usd' => MoneyResource::fromMinor($this->amount_usd_minor, 'USD', 2),
            'amount_lak' => MoneyResource::fromMinor($this->amount_lak_minor, 'LAK', 0),

            // rate ເປັນ string — ຄ່ານີ້ຄື snapshot ທີ່ຕ້ອງກົງເປັນຕົວອັກສອນ
            'fx_rate_per_usd' => (string) $this->fx_rate_per_usd,
            'fx_rate_lak_per_usd' => (string) $this->fx_rate_lak_per_usd,
            // ອັດຕາເປັນກີບ — ສິ່ງທີ່ຟອມສະແດງ ແລະ ສົ່ງຄືນມາເປັນ fx_rate_lak
            'fx_rate_lak' => $this->lakQuote(),

            // ---------- Workflow ----------
            'status' => $this->status,
            'is_locked' => $this->isLocked(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,

            // ສິດຂອງຜູ້ໃຊ້ປັດຈຸບັນຕໍ່ແຖວນີ້ — ເບິ່ງເຫດຜົນເຕັມທີ່ trait
            'can' => $this->permissionsFor($request),

            'reference' => $this->reference,
            'note' => $this->note,
            'attachments_count' => $this->attachments_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'payer' => $this->whenLoaded('payer', fn (): ?array => $this->payer === null ? null : [
                'id' => $this->payer->id,
                'name' => $this->payer->name,
            ]),

            // ທະນາຄານທີ່ເງິນເຂົ້າ — ຄູ່ກັບໃບໂອນທີ່ແນບເປັນຫຼັກຖານ
            'bank' => $this->whenLoaded('bank', fn (): ?array => $this->bank === null ? null : [
                'id' => $this->bank->id,
                'code' => $this->bank->code,
                'name_lo' => $this->bank->name_lo,
                'short_name' => $this->bank->short_name,
            ]),
        ];
    }
}

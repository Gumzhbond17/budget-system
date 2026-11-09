<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExposesLakQuote;
use App\Http\Resources\Concerns\ExposesWorkflowPermissions;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
final class ExpenseResource extends JsonResource
{
    use ExposesLakQuote;
    use ExposesWorkflowPermissions;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // uuid ເທົ່ານັ້ນ — ຫ້າມສົ່ງ auto-increment id ອອກ (ກັນ enumeration, ພາກ 9)
            'uuid' => $this->uuid,
            'doc_no' => $this->doc_no,
            'expense_date' => $this->expense_date->toDateString(),
            'title' => $this->title,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity' => $this->quantity,

            // ---------- ຈຳນວນເງິນ ----------
            'amount' => MoneyResource::make($this->amount_minor),
            'amount_usd' => MoneyResource::fromMinor($this->amount_usd_minor, 'USD', 2),
            'amount_lak' => MoneyResource::fromMinor($this->amount_lak_minor, 'LAK', 0),

            // rate ເປັນ string — ຄ່ານີ້ຄື snapshot ທີ່ຕ້ອງກົງເປັນຕົວອັກສອນ
            'fx_rate_per_usd' => (string) $this->fx_rate_per_usd,
            'fx_rate_lak_per_usd' => (string) $this->fx_rate_lak_per_usd,
            // ອັດຕາເປັນກີບ — ສິ່ງທີ່ຟອມສະແດງ ແລະ ສົ່ງຄືນມາເປັນ fx_rate_lak
            'fx_rate_lak' => $this->lakQuote(),

            // ---------- ການຈ່າຍ ----------
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'paid_at' => $this->paid_at?->toDateString(),

            // ---------- Workflow ----------
            'status' => $this->status,
            'is_locked' => $this->isLocked(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,

            // ---------- ສິດຂອງຜູ້ໃຊ້ປັດຈຸບັນຕໍ່ແຖວນີ້ ----------
            // UI ຫ້າມຄິດເອງວ່າໃຜກົດປຸ່ມໃດໄດ້ — ໃຫ້ Policy ຕອບມາເລີຍ.
            // ເຫດຜົນ: ກົດ segregation of duties ("ຜູ້ສ້າງອະນຸມັດເອງບໍ່ໄດ້")
            // ຕ້ອງໃຊ້ `created_by` ເຊິ່ງເປັນ id ພາຍໃນທີ່ຫ້າມສົ່ງອອກ (ພາກ 9).
            // ສົ່ງ **ຜົນການກວດ** ແທນ **ຄ່າດິບ** ຈຶ່ງບໍ່ຮົ່ວ id ແລະ ບໍ່ມີ
            // logic ອະນຸຍາດຊ້ຳກັນສອງບ່ອນທີ່ຈະເລື່ອນອອກຈາກກັນພາຍຫຼັງ.
            'can' => $this->permissionsFor($request),

            // ---------- ຫຼັກຖານ ----------
            'attachments_count' => $this->attachments_count,
            'has_evidence' => (bool) $this->has_evidence,

            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // ---------- ຄວາມສຳພັນ (ສະເພາະທີ່ eager load ມາແລ້ວ — ກັນ N+1) ----------
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name_lo' => $this->category->name_lo,
                'color' => $this->category->color,
            ]),
            'payer' => $this->whenLoaded('payer', fn (): array => [
                'id' => $this->payer->id,
                'name' => $this->payer->name,
            ]),
            'payee' => $this->whenLoaded('payee', fn (): array => [
                'id' => $this->payee->id,
                'name' => $this->payee->name,
            ]),

            // ທະນາຄານທີ່ໂອນຜ່ານ — ຄູ່ກັບໃບໂອນທີ່ແນບເປັນຫຼັກຖານ.
            // ບໍ່ສົ່ງ `logo_url` ມານຳ: ນັ້ນເປັນ signed URL ທີ່ຕ້ອງເຊັນໃໝ່ຕໍ່ 1 ແຖວ
            // ເຊິ່ງແພງໂດຍບໍ່ຈຳເປັນສຳລັບຕາຕະລາງທຸລະກຳ (ຢູ່ນັ້ນສະແດງແຕ່ຊື່)
            'bank' => $this->whenLoaded('bank', fn (): ?array => $this->bank === null ? null : [
                'id' => $this->bank->id,
                'code' => $this->bank->code,
                'name_lo' => $this->bank->name_lo,
                'short_name' => $this->bank->short_name,
            ]),
        ];
    }
}

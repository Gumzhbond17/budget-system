<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ExposesWorkflowPermissions;
use App\Models\Expense;
use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * ລາຍການທີ່ລໍຖ້າຜູ້ໃຊ້ປັດຈຸບັນອະນຸມັດ — ໃຊ້ຮ່ວມກັນທັງລາຍຈ່າຍ ແລະ ລາຍຮັບ.
 *
 * ເປັນຫຍັງບໍ່ໃຊ້ ExpenseResource/RevenueResource ຊື່ໆ:
 *   1. ກະດິ່ງແຈ້ງເຕືອນຕ້ອງການ **ຂ້າມໂຄງການ** ຈຶ່ງຕ້ອງມີຊື່ໂຄງການຕິດມາ
 *      ເຊິ່ງສອງ resource ນັ້ນບໍ່ສົ່ງ (ຢູ່ໜ້າທຸລະກຳ ໂຄງການຖືກເລືອກໄວ້ແລ້ວ).
 *   2. ຕ້ອງຮູ້ວ່າ **ໃຜສົ່ງມາ** — ຂໍ້ຄວາມ "ທ້າວ ພັນ ສົ່ງ EXP-0042 ມາອະນຸມັດ".
 *   3. ສອງຊະນິດຕ້ອງມາລວມເປັນລາຍການດຽວ ຮຽງຕາມເວລາ ຈຶ່ງຕ້ອງມີຮູບຮ່າງດຽວກັນ.
 *
 * ⚠️ ສົ່ງແຕ່ **ຊື່** ຜູ້ສົ່ງ ບໍ່ແມ່ນ `created_by` — id ພາຍໃນຫ້າມອອກ (ພາກ 9).
 * ຊື່ບໍ່ຮົ່ວຫຍັງເພີ່ມ: ຄົນທີ່ເຫັນແຖວນີ້ເປັນສະມາຊິກໂຄງການດຽວກັນຢູ່ແລ້ວ.
 *
 * @mixin Expense|Revenue
 */
final class PendingApprovalResource extends JsonResource
{
    use ExposesWorkflowPermissions;

    /**
     * ບີບຊະນິດໃຫ້ແຄບກວ່າ `JsonResource` ທຳມະດາ (ທີ່ຮັບຫຍັງກໍໄດ້).
     *
     * ນອກຈາກເປັນເອກະສານໃນຕົວແລ້ວ ຍັງເຮັດໃຫ້ `entryDate()` ແຍກສອງຊະນິດ
     * ໄດ້ຄົບຖ້ວນ — ຖ້າປະໄວ້ເປັນ `mixed` ຈະບໍ່ມີທາງພິສູດວ່າ match ຄຸມໝົດ.
     * ສົ່ງແບບອື່ນເຂົ້າມາ = TypeError ທັນທີ ເຊິ່ງຄືພຶດຕິກຳທີ່ຕ້ອງການ.
     */
    public function __construct(private readonly Expense|Revenue $entry)
    {
        parent::__construct($entry);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isExpense = $this->resource instanceof Expense;

        return [
            // ຕົວແຍກຊະນິດ — SPA ໃຊ້ເລືອກ endpoint ຕອນກົດອະນຸມັດ
            // (`/expenses/{uuid}/approve` ຫຼື `/revenues/{uuid}/approve`)
            // ແລະ ໃຊ້ເລືອກສີ (ລາຍຮັບຂຽວ / ລາຍຈ່າຍແດງ) ຄືກັບໜ້າບັນຊີແຍກປະເພດ
            'type' => $isExpense ? 'expense' : 'revenue',

            'uuid' => $this->uuid,
            'doc_no' => $this->doc_no,
            'title' => $this->title,

            // ສອງຕາຕະລາງຕັ້ງຊື່ຄໍລຳວັນທີຕ່າງກັນ — ປັບໃຫ້ເປັນຊື່ດຽວຢູ່ນີ້
            // ເພື່ອບໍ່ໃຫ້ SPA ຕ້ອງຖາມວ່າ "ແຖວນີ້ຊະນິດຫຍັງ" ກ່ອນອ່ານວັນທີ
            'date' => $this->entryDate()->toDateString(),

            'amount' => MoneyResource::make($this->amount_minor),
            'amount_usd' => MoneyResource::fromMinor($this->amount_usd_minor, 'USD', 2),

            /*
             * ⚠️ ບໍ່ມີຄໍລຳ `submitted_at` ໃນຖານຂໍ້ມູນ — `updated_at` ຄືເວລາ
             * ທີ່ໃກ້ຄຽງທີ່ສຸດ ເພາະການສົ່ງອະນຸມັດຄືການແກ້ status ຄັ້ງສຸດທ້າຍ
             * ຂອງແຖວທີ່ຍັງຢູ່ສະຖານະ `submitted`.
             *
             * ບໍ່ແມ່ນເວລາສົ່ງແທ້ 100%: ແນບຫຼັກຖານເພີ່ມຫຼັງສົ່ງຈະດັນມັນໄປໜ້າ.
             * ພຽງພໍສຳລັບການຮຽງ "ອັນໃໝ່ຢູ່ເທິງ" ແລະ ຂໍ້ຄວາມ "5 ນາທີກ່ອນ".
             * ຖ້າມື້ໃດຕ້ອງການເວລາສົ່ງແທ້ ໃຫ້ເພີ່ມຄໍລຳ `submitted_at`.
             */
            'submitted_at' => $this->updated_at?->toIso8601String(),
            'submitted_by' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),

            'project' => $this->whenLoaded('project', fn (): array => [
                'uuid' => $this->project->uuid,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),

            'attachments_count' => $this->attachments_count,

            // ຄືກັບສອງ resource ຫຼັກ — Policy ເປັນຜູ້ຕອບ ບໍ່ແມ່ນ UI ຄິດເອງ.
            // ຢູ່ນີ້ approve/reject ຄວນເປັນ true ທຸກແຖວ (ຕົວ query ກັ່ນມາແລ້ວ)
            // ແຕ່ຍັງສົ່ງໄປ ເພື່ອໃຫ້ປຸ່ມຢູ່ໜ້າ /approvals ໃຊ້ກົດອັນດຽວກັນ
            // ກັບຕາຕະລາງທຸລະກຳ ແລະ ຫາຍໄປເອງຖ້າມີຄົນຕັດໜ້າໄປອະນຸມັດກ່ອນ.
            'can' => $this->permissionsFor($request),
        ];
    }

    /**
     * ວັນທີຂອງລາຍການ — ຄົນລະຄໍລຳໃນສອງຕາຕະລາງ.
     *
     * ອ່ານຜ່ານ `$this->entry` ທີ່ມີຊະນິດຊັດເຈນ ບໍ່ແມ່ນ `$this->` ໂດຍກົງ:
     * `@mixin` ທີ່ເປັນ union ບອກບໍ່ໄດ້ວ່າຄໍລຳໃດມີຢູ່ຝັ່ງໃດ ເພາະແຕ່ລະຄໍລຳ
     * ມີໃນແບບດຽວເທົ່ານັ້ນ.
     */
    private function entryDate(): Carbon
    {
        return $this->entry instanceof Expense
            ? $this->entry->expense_date
            : $this->entry->received_date;
    }
}

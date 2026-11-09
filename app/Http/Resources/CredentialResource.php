<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Credential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Credential
 *
 * ⚠️ **ບໍ່ມີ `password` ຢູ່ນີ້ເດັດຂາດ** (Model ກໍ #[Hidden] ໄວ້ອີກຊັ້ນ).
 * ທາງດຽວທີ່ຄ່າຈິງຈະອອກຈາກ server ຄື POST /credentials/{uuid}/reveal ພ້ອມ
 * ລະຫັດຜ່ານຂອງຜູ້ຂໍເອງ — ເບິ່ງ CredentialController::reveal.
 *
 * ຫຼັກການດຽວກັນກັບ PayeeResource ທີ່ບໍ່ເຄີຍຄືນ `bank_account_no` ເຕັມ ແລະ
 * BankResource ທີ່ບໍ່ເຄີຍຄືນ `logo_path`: ຄ່າລັບບໍ່ໄຫຼອອກໄປກັບລາຍການ.
 */
final class CredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'url_link' => $this->url_link,
            'username' => $this->username,

            // ຄ່າແທນທີ່ໜ້າຈໍສະແດງ — ຄົງທີ່ ບໍ່ບອກຄວາມຍາວຂອງລະຫັດຈິງ
            'password_masked' => Credential::MASK,

            'note' => $this->note,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toIso8601String(),

            // ມີສະເພາະຕອນດຶງລາຍການ (withMax) — ບອກວ່າມີໃຜເປີດເບິ່ງລະຫັດນີ້
            // ຄັ້ງສຸດທ້າຍເມື່ອໃດ. null = ໂຫຼດມາແລ້ວແຕ່ຍັງບໍ່ມີໃຜເປີດເລີຍ.
            //
            // ກວດດ້ວຍ array_key_exists ບໍ່ແມ່ນ `!== null`: ທັງສອງກໍລະນີ
            // ("ບໍ່ໄດ້ຂໍມາ" ກັບ "ຍັງບໍ່ມີໃຜເປີດ") ໃຫ້ຄ່າ null ຄືກັນ ແຕ່ໜ້າຈໍ
            // ຕ້ອງແຍກໃຫ້ອອກ. ຄ່າຈາກ withMax ເປັນ string ດິບຂອງ MySQL —
            // ບໍ່ຜ່ານ cast ເພາະບໍ່ແມ່ນຄໍລຳຈິງ ຈຶ່ງຕ້ອງແປງເອງ.
            'last_revealed_at' => $this->when(
                array_key_exists('last_revealed_at', $this->getAttributes()),
                fn () => $this->last_revealed_at === null
                    ? null
                    : Carbon::parse($this->last_revealed_at)->toIso8601String(),
            ),
        ];
    }
}

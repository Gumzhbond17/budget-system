<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\HasEvidence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use LogicException;

/**
 * docs/architecture.md ພາກ 4.2 — ດັດແປງໃຫ້ຮັບໄດ້ທັງລາຍຈ່າຍ ແລະ ລາຍຮັບ.
 */
final class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy ກວດວ່າຜູ້ໃຊ້ເປັນສະມາຊິກໂຄງການ ແລະ ລາຍການຍັງບໍ່ອະນຸມັດ
        return $this->user()->can('attach', $this->attachable());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'required',
                // HEIC ບໍ່ຢູ່ໃນລາຍການ — GD ອ່ານບໍ່ໄດ້, ຈະລົ້ມຕອນ re-encode
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])
                    ->max(12 * 1024),   // 12 MB — ຮູບຈາກມືຖືສະໄໝໃໝ່ໃຫຍ່ໄດ້
            ],
            'kind' => ['required', 'in:transfer_slip,receipt,invoice,quotation,contract,photo,other'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'ກະລຸນາເລືອກໄຟລ໌ຢ່າງໜ້ອຍ 1 ໄຟລ໌',
            'files.max' => 'ອັບໂຫຼດໄດ້ສູງສຸດ 10 ໄຟລ໌ຕໍ່ຄັ້ງ',
            'files.*.max' => 'ໄຟລ໌ໃຫຍ່ເກີນ 12 MB ກະລຸນາຫຍໍ້ຮູບກ່ອນ',
            'files.*.mimes' => 'ຮອງຮັບສະເພາະ JPG, PNG, WEBP, PDF ເທົ່ານັ້ນ',
            'kind.required' => 'ກະລຸນາເລືອກປະເພດຫຼັກຖານ',
        ];
    }

    /**
     * ລາຍການທີ່ຫຼັກຖານຈະຖືກແນບໃສ່ — expense ຫຼື revenue ແລ້ວແຕ່ route.
     *
     * @return Model&HasEvidence
     */
    public function attachable(): HasEvidence
    {
        $model = $this->route('expense') ?? $this->route('revenue');

        // ກວດແທນທີ່ຈະ cast — ຖ້າ route ຖືກຕໍ່ໃໝ່ຜິດ ຢາກໃຫ້ລົ້ມດັງໆຢູ່ນີ້
        // ບໍ່ແມ່ນລົ້ມແບບງຶນງັນຢູ່ໃນ AttachmentService
        if (! $model instanceof Model || ! $model instanceof HasEvidence) {
            throw new LogicException('Route ນີ້ຕ້ອງ bind expense ຫຼື revenue');
        }

        return $model;
    }
}

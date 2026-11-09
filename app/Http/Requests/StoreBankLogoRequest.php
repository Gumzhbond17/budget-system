<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Bank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use LogicException;

/**
 * ອັບໂຫຼດໂລໂກ້ທະນາຄານ.
 *
 * ອະນຸຍາດແມ່ນອັນດຽວກັບ "ແກ້ໄຂທະນາຄານ" — ໂລໂກ້ຄືຄຸນລັກສະນະໜຶ່ງຂອງແຖວ
 * ບໍ່ແມ່ນຊັບພະຍາກອນຕ່າງຫາກທີ່ມີສິດຂອງຕົນເອງ.
 */
final class StoreBankLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->bank());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                // ບໍ່ມີ svg: ເປັນ markup ທີ່ຝັງ <script> ໄດ້ ແລະ GD decode ບໍ່ໄດ້ຢູ່ແລ້ວ.
                // ບໍ່ມີ gif: ພາບເຄື່ອນໄຫວບໍ່ແມ່ນສິ່ງທີ່ໂລໂກ້ຕ້ອງການ ແລະ WebP
                // ຈາກ GD ຈະໄດ້ frame ດຽວແບບງຽບໆ.
                File::types(['jpg', 'jpeg', 'png', 'webp'])
                    // 4 MB — ໂລໂກ້ຈິງບໍ່ເຖິງ 200 KB, ຂອບເຂດນີ້ໄວ້ກັນການວາງຜິດໄຟລ໌
                    ->max(4 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'ກະລຸນາເລືອກໄຟລ໌ໂລໂກ້',
            'logo.max' => 'ໄຟລ໌ໃຫຍ່ເກີນ 4 MB — ໂລໂກ້ບໍ່ຄວນໃຫຍ່ຂະໜາດນັ້ນ',
            'logo.mimes' => 'ຮອງຮັບສະເພາະ JPG, PNG, WEBP ເທົ່ານັ້ນ',
        ];
    }

    public function bank(): Bank
    {
        $bank = $this->route('bank');

        // ກວດແທນທີ່ຈະ cast — ຖ້າ route ຖືກຕໍ່ໃໝ່ຜິດ ຢາກໃຫ້ລົ້ມດັງໆຢູ່ນີ້
        if (! $bank instanceof Bank) {
            throw new LogicException('Route ນີ້ຕ້ອງ bind bank');
        }

        return $bank;
    }
}

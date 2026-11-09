<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * ອັບໂຫຼດຮູບໂປຣໄຟລ໌ຂອງ **ຕົນເອງ** — ບໍ່ມີ route ໃຫ້ປ່ຽນຮູບຄົນອື່ນ.
 */
final class StoreAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('updateAvatar', $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                // ບໍ່ມີ svg/gif — ເຫດຜົນດຽວກັບໂລໂກ້ທະນາຄານ (StoreBankLogoRequest)
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(8 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'ກະລຸນາເລືອກຮູບ',
            'avatar.max' => 'ຮູບໃຫຍ່ເກີນ 8 MB',
            'avatar.mimes' => 'ຮອງຮັບສະເພາະ JPG, PNG, WEBP ເທົ່ານັ້ນ',
        ];
    }
}

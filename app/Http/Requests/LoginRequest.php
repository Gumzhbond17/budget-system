<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'ກະລຸນາປ້ອນອີເມວ',
            'email.email' => 'ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ',
            'password.required' => 'ກະລຸນາປ້ອນລະຫັດຜ່ານ',
        ];
    }
}

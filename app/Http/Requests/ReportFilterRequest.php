<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ຕົວກັ່ນຕອງຮ່ວມຂອງລາຍງານທຸກສະບັບ — ໜ້າຈໍ ແລະ PDF ໃຊ້ກົດອັນດຽວກັນ.
 *
 * ຖ້າສອງບ່ອນນີ້ validate ຄົນລະແບບ ຕົວເລກທີ່ພິມອອກມາຈະຕ່າງຈາກທີ່ເຫັນຢູ່ໜ້າຈໍ
 * ໂດຍທີ່ບໍ່ມີໃຜສັງເກດເຫັນ.
 *
 * ຄລາສລູກກຳນົດພຽງ **ໃຜເບິ່ງໄດ້** (`authorize()`) ແລະ ມິຕິພິເສດຂອງຕົນ —
 * ເຊັ່ນ ໝວດ ທີ່ມີແຕ່ລາຍຈ່າຍເທົ່ານັ້ນ.
 */
abstract class ReportFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'payer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],

            // 'all' ຫຼື array ຂອງສະຖານະ — ຄ່າຕັ້ງຕົ້ນຄື approved ເທົ່ານັ້ນ
            'statuses' => ['nullable', function (string $attribute, mixed $value, callable $fail): void {
                if ($value !== 'all' && ! is_array($value)) {
                    $fail('statuses ຕ້ອງເປັນ "all" ຫຼື array ຂອງສະຖານະ');
                }
            }],
            'statuses.*' => ['in:draft,submitted,approved,rejected'],

            ...$this->extraRules(),
        ];
    }

    /**
     * ມິຕິທີ່ມີສະເພາະລາຍງານນັ້ນ.
     *
     * @return array<string, mixed>
     */
    protected function extraRules(): array
    {
        return [];
    }
}

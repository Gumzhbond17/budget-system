<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Contracts\Validation\Validator;

final class UpdateExpenseRequest extends ExpenseRequest
{
    public function authorize(): bool
    {
        $expense = $this->existingExpense();

        // ExpensePolicy::update() ປະຕິເສດລາຍການທີ່ approved ແລ້ວທຸກກໍລະນີ
        return $expense !== null
            && $this->user()?->can('update', $expense) === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = $this->baseRules();

        // ແກ້ບາງ field ໄດ້ — ແຕ່ຖ້າສົ່ງມາຕ້ອງບໍ່ຫວ່າງ
        foreach (['expense_date', 'title', 'currency_code', 'amount'] as $field) {
            array_unshift($rules[$field], 'sometimes', 'required');
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        // ປ່ຽນສະກຸນເງິນຕ້ອງລະບຸ amount ໃໝ່ — minor unit ຂອງແຕ່ລະສະກຸນບໍ່ຄືກັນ.
        // ExpenseService::update() ບັງຄັບຊ້ຳ ແຕ່ຈັບຢູ່ນີ້ຈະໄດ້ 422 ບໍ່ແມ່ນ 500.
        // ກວດວ່າ "ປ່ຽນຈິງ" ບໍ່ແມ່ນແຄ່ "ສົ່ງມາ" — ສົ່ງສະກຸນເດີມມາຊ້ຳບໍ່ຄວນຖືກປະຕິເສດ.
        $validator->after(function (Validator $validator): void {
            $expense = $this->existingExpense();
            $currency = $this->input('currency_code');

            if ($expense === null || ! is_string($currency)) {
                return;
            }

            if ($currency !== $expense->currency_code && ! $this->has('amount')) {
                $validator->errors()->add('amount', 'ປ່ຽນສະກຸນເງິນຕ້ອງສົ່ງຈຳນວນເງິນໃໝ່ມານຳ');
            }
        });
    }

    protected function existingExpense(): ?Expense
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense ? $expense : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\Project;

final class StoreExpenseRequest extends ExpenseRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('create', [Expense::class, $project]) === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = $this->baseRules();

        // field ທີ່ຂາດບໍ່ໄດ້ຕອນສ້າງ — ຍອດແປງຄິດຈາກສາມອັນນີ້
        foreach (['expense_date', 'title', 'currency_code', 'amount'] as $field) {
            array_unshift($rules[$field], 'required');
        }

        return $rules;
    }

    protected function existingExpense(): ?Expense
    {
        return null;
    }
}

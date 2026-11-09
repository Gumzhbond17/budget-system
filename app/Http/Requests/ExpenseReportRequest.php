<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\Project;

final class ExpenseReportRequest extends ReportFilterRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        // ສິດຂອງລາຍງານ = ສິດເບິ່ງລາຍຈ່າຍ (ຕ້ອງເປັນສະມາຊິກໂຄງການ) —
        // ລາຍງານຄືຂໍ້ມູນອັນດຽວກັນ ພຽງແຕ່ຈັດຮູບແບບໃໝ່
        return $project instanceof Project
            && $this->user()?->can('viewAny', [Expense::class, $project]) === true;
    }

    /**
     * ໝວດມີສະເພາະລາຍຈ່າຍ — ລາຍຮັບບໍ່ມີມິຕິນີ້.
     *
     * @return array<string, mixed>
     */
    protected function extraRules(): array
    {
        return ['category_id' => ['nullable', 'integer']];
    }
}

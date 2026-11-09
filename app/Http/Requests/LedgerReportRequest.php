<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\Project;

final class LedgerReportRequest extends ReportFilterRequest
{
    public function authorize(): bool
    {
        // ໃຊ້ ExpensePolicy::viewAny ເປັນປະຕູ — ເຫັນ GL ໄດ້ = ເປັນສະມາຊິກໂຄງການ.
        // RevenuePolicy ມີກົດດຽວກັນ ຈຶ່ງບໍ່ຕ້ອງກວດສອງເທື່ອ (ຄືກັບ LedgerController).
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('viewAny', [Expense::class, $project]) === true;
    }
}

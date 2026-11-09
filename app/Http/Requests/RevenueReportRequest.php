<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Revenue;

final class RevenueReportRequest extends ReportFilterRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('viewAny', [Revenue::class, $project]) === true;
    }
}

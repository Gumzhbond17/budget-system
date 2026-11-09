<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Payer;
use App\Models\Project;
use App\Models\User;

/**
 * ປະກອບຂໍ້ມູນລາຍງານລາຍຮັບ ແລ້ວສົ່ງໃຫ້ ReportPdfRenderer ພິມ.
 */
final class RevenueReportPdf
{
    public function __construct(
        private RevenueReportService $reports,
        private ReportPdfRenderer $renderer,
    ) {}

    public function render(Project $project, ReportFilters $filters, User $generatedBy): string
    {
        return $this->renderer->render('reports.revenues', [
            'project' => $project,
            'filters' => $filters,

            'payerLabel' => $filters->payerId === null
                ? 'ທຸກແຫຼ່ງທຶນ'
                : (Payer::query()->whereKey($filters->payerId)->value('name') ?? '—'),

            'summary' => $this->reports->summary($project, $filters),
            'byPayer' => $this->reports->byPayer($project, $filters),
            'revenues' => $this->reports->detailedList($project, $filters),
            'generatedAt' => now(),
            'generatedBy' => $generatedBy->name,
        ]);
    }

    /** ຊື່ໄຟລ໌ທີ່ຜູ້ໃຊ້ຈະເຫັນຕອນດາວໂຫຼດ — ASCII ລ້ວນ ເພື່ອບໍ່ໃຫ້ header ພັງ */
    public function filename(Project $project): string
    {
        return sprintf('revenues-%s-%s.pdf', $project->code, now()->format('Ymd-His'));
    }
}

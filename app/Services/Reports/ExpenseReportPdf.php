<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ExpenseCategory;
use App\Models\Payer;
use App\Models\Project;
use App\Models\User;

/**
 * ປະກອບຂໍ້ມູນລາຍງານລາຍຈ່າຍ ແລ້ວສົ່ງໃຫ້ ReportPdfRenderer ພິມ.
 *
 * ຕົວກັ່ນຕອງຊຸດດຽວກັນກັບທີ່ ReportController@expenses ໃຊ້ — ໃບທີ່ພິມອອກມາ
 * ຈຶ່ງກົງກັບທີ່ເຫັນຢູ່ໜ້າຈໍທຸກແຖວ.
 */
final class ExpenseReportPdf
{
    public function __construct(
        private ExpenseReportService $reports,
        private ReportPdfRenderer $renderer,
    ) {}

    public function render(Project $project, ReportFilters $filters, User $generatedBy): string
    {
        return $this->renderer->render('reports.expenses', [
            'project' => $project,
            'filters' => $filters,

            // ຫົວລາຍງານຕ້ອງບອກຊື່ ບໍ່ແມ່ນ id — ໃບທີ່ພິມອອກມາຕ້ອງອ່ານໄດ້ດ້ວຍຕົນເອງ
            'payerLabel' => $filters->payerId === null
                ? 'ທຸກຜູ້ຈ່າຍ'
                : (Payer::query()->whereKey($filters->payerId)->value('name') ?? '—'),
            'categoryLabel' => $filters->categoryId === null
                ? 'ທຸກໝວດ'
                : (ExpenseCategory::query()->whereKey($filters->categoryId)->value('name_lo') ?? '—'),

            'summary' => $this->reports->summary($project, $filters),
            'byPayer' => $this->reports->byPayer($project, $filters),
            'expenses' => $this->reports->detailedList($project, $filters),
            'generatedAt' => now(),
            'generatedBy' => $generatedBy->name,
        ]);
    }

    /** ຊື່ໄຟລ໌ທີ່ຜູ້ໃຊ້ຈະເຫັນຕອນດາວໂຫຼດ — ASCII ລ້ວນ ເພື່ອບໍ່ໃຫ້ header ພັງ */
    public function filename(Project $project): string
    {
        return sprintf('expenses-%s-%s.pdf', $project->code, now()->format('Ymd-His'));
    }
}

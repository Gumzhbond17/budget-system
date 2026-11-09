<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Payer;
use App\Models\Project;
use App\Models\User;

/**
 * ລາຍງານລາຍຮັບ-ລາຍຈ່າຍ (ບັນຊີແຍກປະເພດ) — ສ່ວນສະຫຼຸບ + ສ່ວນລະອຽດ ໃນສະບັບດຽວ.
 */
final class LedgerReportPdf
{
    public function __construct(
        private GeneralLedgerService $ledger,
        private ReportPdfRenderer $renderer,
    ) {}

    public function render(Project $project, ReportFilters $filters, User $generatedBy): string
    {
        $ledger = $this->ledger->forProject($project, $filters);

        return $this->renderer->render('reports.ledger', [
            'project' => $project,
            'filters' => $filters,

            'payerLabel' => $filters->payerId === null
                ? 'ທຸກຜູ້ຈ່າຍ'
                : (Payer::query()->whereKey($filters->payerId)->value('name') ?? '—'),

            'entries' => $ledger['entries'],
            'totals' => $ledger['totals'],
            // ຄິດຈາກແຖວຊຸດດຽວກັນ — ສ່ວນສະຫຼຸບກັບສ່ວນລະອຽດຈຶ່ງກົງກັນສະເໝີ
            'monthly' => $this->ledger->monthlySummary($ledger['entries']),
            'generatedAt' => now(),
            'generatedBy' => $generatedBy->name,
        ]);
    }

    /** ຊື່ໄຟລ໌ທີ່ຜູ້ໃຊ້ຈະເຫັນຕອນດາວໂຫຼດ — ASCII ລ້ວນ ເພື່ອບໍ່ໃຫ້ header ພັງ */
    public function filename(Project $project): string
    {
        return sprintf('ledger-%s-%s.pdf', $project->code, now()->format('Ymd-His'));
    }
}

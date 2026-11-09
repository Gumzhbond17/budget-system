<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RevenueResource;
use App\Models\Revenue;
use App\Services\RevenueService;
use Illuminate\Http\Request;

/**
 * draft → submitted → approved ──reopen (ຜູ້ດູແລລະບົບ)──┐
 *                   ↘ rejected → submitted            │
 *                        ↑────────────────────────────┘
 *
 * ຄືກັນກັບ ExpenseWorkflowController: Policy ຄຸມ **ໃຜ**, Service ຄຸມ **ຈາກສະຖານະໃດໄປໃດ**.
 */
final class RevenueWorkflowController extends Controller
{
    public function __construct(private RevenueService $revenues) {}

    public function submit(Revenue $revenue): RevenueResource
    {
        $this->authorize('submit', $revenue);

        return RevenueResource::make($this->revenues->submit($revenue));
    }

    public function approve(Request $request, Revenue $revenue): RevenueResource
    {
        $this->authorize('approve', $revenue);

        return RevenueResource::make(
            $this->revenues->approve($revenue, (int) $request->user()->id)
        );
    }

    public function reject(Request $request, Revenue $revenue): RevenueResource
    {
        $this->authorize('reject', $revenue);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return RevenueResource::make(
            $this->revenues->reject($revenue, (int) $request->user()->id, $validated['reason'])
        );
    }

    /** ຍົກເລີກການອະນຸມັດ ສົ່ງກັບໃຫ້ຜູ້ບັນທຶກແກ້ — ເບິ່ງ ExpenseWorkflowController::reopen() */
    public function reopen(Request $request, Revenue $revenue): RevenueResource
    {
        $this->authorize('reopen', $revenue);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return RevenueResource::make(
            $this->revenues->reopen($revenue, (int) $request->user()->id, $validated['reason'])
        );
    }
}

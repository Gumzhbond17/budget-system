<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\Request;

/**
 * draft → submitted → approved ──reopen (ຜູ້ດູແລລະບົບ)──┐
 *                   ↘ rejected → submitted            │
 *                        ↑────────────────────────────┘
 *
 * ການແບ່ງໜ້າທີ່: Policy ຄຸມ **ໃຜ** ເຮັດໄດ້ (ລວມທັງ segregation of duties),
 * ExpenseService ຄຸມ **ຈາກສະຖານະໃດໄປໃດ** ໄດ້.
 */
final class ExpenseWorkflowController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function submit(Expense $expense): ExpenseResource
    {
        $this->authorize('submit', $expense);

        return ExpenseResource::make($this->expenses->submit($expense));
    }

    public function approve(Request $request, Expense $expense): ExpenseResource
    {
        $this->authorize('approve', $expense);

        return ExpenseResource::make(
            $this->expenses->approve($expense, (int) $request->user()->id)
        );
    }

    public function reject(Request $request, Expense $expense): ExpenseResource
    {
        $this->authorize('reject', $expense);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return ExpenseResource::make(
            $this->expenses->reject($expense, (int) $request->user()->id, $validated['reason'])
        );
    }

    /** ຍົກເລີກການອະນຸມັດ ສົ່ງກັບໃຫ້ຜູ້ບັນທຶກແກ້ — ຕ້ອງມີເຫດຜົນ ຜູ້ບັນທຶກຈະເຫັນ */
    public function reopen(Request $request, Expense $expense): ExpenseResource
    {
        $this->authorize('reopen', $expense);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        return ExpenseResource::make(
            $this->expenses->reopen($expense, (int) $request->user()->id, $validated['reason'])
        );
    }
}

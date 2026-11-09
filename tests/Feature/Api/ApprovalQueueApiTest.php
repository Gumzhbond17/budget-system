<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Project;
use App\Models\Revenue;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\RevenueService;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

/**
 * ຄິວລໍຖ້າອະນຸມັດ — ແຫຼ່ງຂໍ້ມູນຂອງກະດິ່ງແຈ້ງເຕືອນ ແລະ ໜ້າ `/approvals`.
 *
 * ຄຸນສົມບັດທີ່ຕ້ອງຮັກສາ: ສິ່ງທີ່ປາກົດຢູ່ຄິວ **ຕ້ອງກົດອະນຸມັດໄດ້ຈິງ** —
 * ຖ້າສອງຢ່າງນີ້ເລື່ອນອອກຈາກກັນ ຜູ້ໃຊ້ຈະກົດແລ້ວໄດ້ 403/422 ໂດຍບໍ່ຮູ້ສາເຫດ.
 */
beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();

    $this->approver = memberOf($this->project, 'authorize');
    $this->accountant = memberOf($this->project, 'modify');
});

/** ສ້າງລາຍຈ່າຍທີ່ສົ່ງອະນຸມັດແລ້ວ ພ້ອມລໍຖ້າ */
function submittedExpense(Project $project, User $creator): Expense
{
    $expense = createExpense($project, $creator);

    return app(ExpenseService::class)->submit($expense, $creator);
}

function submittedRevenue(Project $project, User $creator): Revenue
{
    $revenue = createRevenue($project, $creator);

    return app(RevenueService::class)->submit($revenue, $creator);
}

describe('ສິ່ງທີ່ຢູ່ໃນຄິວ', function () {
    it('ສະແດງລາຍຈ່າຍ ແລະ ລາຍຮັບ ທີ່ລໍຖ້າອະນຸມັດ ໃນລາຍການດຽວ', function () {
        submittedExpense($this->project, $this->accountant);
        submittedRevenue($this->project, $this->accountant);

        Sanctum::actingAs($this->approver);

        $data = $this->getJson('/api/v1/approvals')->assertOk()->json('data');

        expect($data)->toHaveCount(2)
            ->and(collect($data)->pluck('type')->sort()->values()->all())
            ->toBe(['expense', 'revenue']);
    });

    it('ນັບຈຳນວນເຕັມໄວ້ໃນ meta.total ເຖິງແມ່ນຈະຕັດດ້ວຍ limit', function () {
        submittedExpense($this->project, $this->accountant);
        submittedExpense($this->project, $this->accountant);
        submittedRevenue($this->project, $this->accountant);

        Sanctum::actingAs($this->approver);

        // ປ້າຍເລກເທິງກະດິ່ງອ່ານຄ່ານີ້ — ຖ້າມັນຕາມ limit ເລກຈະຄ້າງ
        $response = $this->getJson('/api/v1/approvals?limit=2')->assertOk();

        expect($response->json('data'))->toHaveCount(2)
            ->and($response->json('meta.total'))->toBe(3);
    });

    it('ສົ່ງຊື່ຜູ້ສົ່ງ ແລະ ໂຄງການມານຳ — ກະດິ່ງຂ້າມໂຄງການຈຶ່ງອ່ານອອກ', function () {
        submittedExpense($this->project, $this->accountant);

        Sanctum::actingAs($this->approver);

        $this->getJson('/api/v1/approvals')
            ->assertOk()
            ->assertJsonPath('data.0.submitted_by', $this->accountant->name)
            ->assertJsonPath('data.0.project.uuid', $this->project->uuid)
            ->assertJsonPath('data.0.can.approve', true)
            ->assertJsonPath('data.0.can.reject', true);
    });

    it('ລວມທຸກໂຄງການທີ່ຜູ້ໃຊ້ເປັນຜູ້ອະນຸມັດ', function () {
        $other = Project::factory()->create();

        // ຄົນດຽວກັນເປັນຜູ້ອະນຸມັດຢູ່ສອງໂຄງການ
        $this->approver->projectMemberships()->create([
            'project_id' => $other->id,
            'role' => 'authorize',
        ]);
        $this->approver->forgetProjectRoles();

        submittedExpense($this->project, $this->accountant);
        submittedExpense($other, memberOf($other, 'modify'));

        Sanctum::actingAs($this->approver);

        expect($this->getJson('/api/v1/approvals')->assertOk()->json('data'))
            ->toHaveCount(2);
    });
});

describe('ສິ່ງທີ່ຕ້ອງບໍ່ຢູ່ໃນຄິວ', function () {
    it('ຮ່າງທີ່ຍັງບໍ່ທັນສົ່ງ', function () {
        createExpense($this->project, $this->accountant);

        Sanctum::actingAs($this->approver);

        expect($this->getJson('/api/v1/approvals')->assertOk()->json('data'))->toBeEmpty();
    });

    it('ລາຍການທີ່ອະນຸມັດໄປແລ້ວ — ຫາຍຈາກຄິວທັນທີ', function () {
        $expense = submittedExpense($this->project, $this->accountant);

        Sanctum::actingAs($this->approver);
        expect($this->getJson('/api/v1/approvals')->json('data'))->toHaveCount(1);

        $this->postJson("/api/v1/expenses/{$expense->uuid}/approve")->assertOk();

        expect($this->getJson('/api/v1/approvals')->json('data'))->toBeEmpty();
    });

    /** ກົດແຍກໜ້າທີ່ — ຄົນສ້າງອະນຸມັດເອງບໍ່ໄດ້ ຈຶ່ງບໍ່ຄວນເຫັນຢູ່ຄິວຕົນ */
    it('ລາຍການທີ່ຜູ້ຖາມສ້າງເອງ', function () {
        // ຜູ້ອະນຸມັດສ້າງເອງ ແລ້ວສົ່ງເອງ
        submittedExpense($this->project, memberOf($this->project, 'full_access'));

        $selfMade = createExpense($this->project, $this->approver);
        app(ExpenseService::class)->submit($selfMade, $this->approver);

        Sanctum::actingAs($this->approver);

        $docNos = collect($this->getJson('/api/v1/approvals')->json('data'))->pluck('doc_no');

        expect($docNos)->toHaveCount(1)
            ->and($docNos)->not->toContain($selfMade->doc_no);
    });

    it('ລາຍການຂອງໂຄງການທີ່ບໍ່ໄດ້ເປັນສະມາຊິກ', function () {
        $other = Project::factory()->create();
        submittedExpense($other, memberOf($other, 'modify'));

        Sanctum::actingAs($this->approver);

        expect($this->getJson('/api/v1/approvals')->assertOk()->json('data'))->toBeEmpty();
    });

    it('ຜູ້ທີ່ບໍ່ມີສິດອະນຸມັດເຫັນຄິວຫວ່າງ ບໍ່ແມ່ນ 403', function () {
        submittedExpense($this->project, $this->accountant);

        // `modify` ສ້າງ/ແກ້ໄດ້ ແຕ່ອະນຸມັດບໍ່ໄດ້
        Sanctum::actingAs($this->accountant);

        $this->getJson('/api/v1/approvals')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    });

    it('ຜູ້ອ່ານຢ່າງດຽວກໍເຫັນຄິວຫວ່າງ', function () {
        submittedExpense($this->project, $this->accountant);

        Sanctum::actingAs(memberOf($this->project, 'read_only'));

        expect($this->getJson('/api/v1/approvals')->assertOk()->json('data'))->toBeEmpty();
    });
});

describe('ສິດເຂົ້າເຖິງ', function () {
    it('ຕ້ອງ login ກ່ອນ', function () {
        $this->getJson('/api/v1/approvals')->assertUnauthorized();
    });

    it('ບໍ່ຮົ່ວ id ພາຍໃນອອກ response', function () {
        submittedExpense($this->project, $this->accountant);

        Sanctum::actingAs($this->approver);

        $row = $this->getJson('/api/v1/approvals')->assertOk()->json('data.0');

        expect($row)->not->toHaveKey('id')
            ->and($row)->not->toHaveKey('created_by')
            ->and($row)->not->toHaveKey('project_id');
    });
});

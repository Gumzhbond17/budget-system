<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Services\ExpenseService;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->accountant = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');
    $this->service = app(ExpenseService::class);

    $this->equipment = ExpenseCategory::factory()->create(['code' => 'EQUIPMENT', 'name_lo' => 'ອຸປະກອນຕ່າງໆ']);
    $this->labor = ExpenseCategory::factory()->create(['code' => 'LABOR', 'name_lo' => 'ຄ່າແຮງ']);

    $this->approved = function (array $overrides = []): Expense {
        $expense = createExpense($this->project, $this->accountant, $overrides);
        $this->service->submit($expense);

        return $this->service->approve($expense, $this->approver->id);
    };

    $this->url = "/api/v1/projects/{$this->project->uuid}/reports/budget-vs-actual";
});

it('ທຽບງົບກັບຈິງໃນ USD ເຖິງວ່າຈະຈ່າຍເປັນກີບ', function () {
    // ງົບ USD 1,000 ສຳລັບອຸປະກອນ
    createBudgetItem($this->project, ['category_id' => $this->equipment->id, 'unit_price' => '1000.00']);

    // ຈ່າຍຈິງ 12,500,000 ກີບ @ 21,760 = $574.45
    ($this->approved)(['category_id' => $this->equipment->id, 'fx_rate_per_usd' => '21760']);

    Sanctum::actingAs($this->accountant);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.total_budget_usd.decimal', '1000.00')
        ->assertJsonPath('data.total_actual_usd.decimal', '574.45')
        ->assertJsonPath('data.total_variance_usd.decimal', '425.55')
        ->assertJsonPath('data.used_percentage', 57.45)
        ->assertJsonPath('data.by_category.0.label', 'ອຸປະກອນຕ່າງໆ')
        ->assertJsonPath('data.by_category.0.is_over_budget', false);
});

it('ໝາຍທຸງເມື່ອໃຊ້ເກີນງົບ', function () {
    createBudgetItem($this->project, ['category_id' => $this->labor->id, 'unit_price' => '100.00']);
    ($this->approved)(['category_id' => $this->labor->id, 'fx_rate_per_usd' => '21760']);   // $574.45

    Sanctum::actingAs($this->accountant);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.by_category.0.is_over_budget', true)
        ->assertJsonPath('data.by_category.0.variance_usd.minor', 10_000 - 57_445)   // ຕິດລົບ
        ->assertJsonPath('data.by_category.0.used_percentage', 574.45);
});

it('ສະແດງງົບທີ່ຍັງບໍ່ໄດ້ໃຊ້ເລີຍ', function () {
    createBudgetItem($this->project, ['category_id' => $this->equipment->id, 'unit_price' => '5000.00']);

    Sanctum::actingAs($this->accountant);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data.by_category')
        ->assertJsonPath('data.by_category.0.budget_usd.decimal', '5000.00')
        ->assertJsonPath('data.by_category.0.actual_usd.minor', 0)
        // json_encode ຕັດ ".0" ຖິ້ມ ດັ່ງນັ້ນ 0.0 ມາເຖິງ client ເປັນ 0
        ->assertJsonPath('data.by_category.0.used_percentage', 0);
});

it('ສະແດງລາຍຈ່າຍທີ່ຢູ່ນອກງົບ', function () {
    // ຈ່າຍໃນໝວດທີ່ບໍ່ໄດ້ຕັ້ງງົບໄວ້ — ນີ້ຄືສິ່ງທີ່ຜູ້ຄຸມໂຄງການຢາກເຫັນທີ່ສຸດ
    ($this->approved)(['category_id' => $this->labor->id, 'fx_rate_per_usd' => '21760']);

    Sanctum::actingAs($this->accountant);

    $response = $this->getJson($this->url)->assertOk();

    expect($response->json('data.by_category'))->toHaveCount(1)
        ->and($response->json('data.by_category.0.label'))->toBe('ຄ່າແຮງ')
        ->and($response->json('data.by_category.0.budget_usd.minor'))->toBe(0)
        ->and($response->json('data.by_category.0.used_percentage'))->toBeNull();
});

it('ຮວມໝວດທີ່ມີທັງງົບ ແລະ ຈິງ ເປັນແຖວດຽວ', function () {
    createBudgetItem($this->project, ['category_id' => $this->equipment->id, 'unit_price' => '1000.00']);
    createBudgetItem($this->project, ['category_id' => $this->labor->id, 'unit_price' => '2000.00']);
    ($this->approved)(['category_id' => $this->equipment->id, 'fx_rate_per_usd' => '21760']);
    ($this->approved)(['category_id' => $this->labor->id, 'fx_rate_per_usd' => '21760']);

    Sanctum::actingAs($this->accountant);

    $rows = $this->getJson($this->url)->assertOk()->json('data.by_category');

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'label'))->toEqualCanonicalizing(['ອຸປະກອນຕ່າງໆ', 'ຄ່າແຮງ']);
});

it('ນັບສະເພາະລາຍຈ່າຍທີ່ອະນຸມັດແລ້ວ', function () {
    createBudgetItem($this->project, ['category_id' => $this->equipment->id, 'unit_price' => '1000.00']);
    createExpense($this->project, $this->accountant, ['category_id' => $this->equipment->id]);   // ຍັງເປັນ draft

    Sanctum::actingAs($this->accountant);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.total_actual_usd.minor', 0);
});

it('ບໍ່ຮົ່ວງົບຂອງໂຄງການອື່ນ', function () {
    createBudgetItem($this->project, ['unit_price' => '9999.00']);

    $other = Project::factory()->create();
    Sanctum::actingAs(memberOf($other, 'full_access'));

    $this->getJson("/api/v1/projects/{$other->uuid}/reports/budget-vs-actual")
        ->assertOk()
        ->assertJsonPath('data.total_budget_usd.minor', 0)
        ->assertJsonPath('data.used_percentage', null);
});

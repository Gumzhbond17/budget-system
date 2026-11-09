<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payer;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;
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
    $this->sinexay = Payer::factory()->create(['name' => 'Sinexay']);

    /** ສ້າງລາຍການແລ້ວອະນຸມັດເລີຍ — ລາຍງານນັບສະເພາະ approved ຕາມຄ່າຕັ້ງຕົ້ນ */
    $this->approved = function (array $overrides = []): Expense {
        $expense = createExpense($this->project, $this->accountant, $overrides);
        $this->service->submit($expense);

        return $this->service->approve($expense, $this->approver->id);
    };
});

describe('summary', function () {
    it('ລວມຍອດ USD ແລະ ກີບຂອງລາຍການທີ່ອະນຸມັດແລ້ວ', function () {
        ($this->approved)(['fx_rate_per_usd' => '21760']);                       // 574.45
        ($this->approved)(['fx_rate_per_usd' => '21760', 'amount' => '5,000,000']); // 229.78

        Sanctum::actingAs($this->accountant);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/summary")
            ->assertOk()
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.total_usd.minor', 57_445 + 22_978)
            ->assertJsonPath('data.total_usd.decimal', '804.23')
            ->assertJsonPath('data.total_lak.minor', 17_500_000);
    });

    it('ບໍ່ນັບລາຍການທີ່ຍັງບໍ່ອະນຸມັດ ແຕ່ຍັງບອກຈຳນວນທີ່ລໍຖ້າ', function () {
        ($this->approved)();
        $this->service->submit(createExpense($this->project, $this->accountant, ['title' => 'ລໍຖ້າ']));
        createExpense($this->project, $this->accountant, ['title' => 'ຮ່າງ']);

        Sanctum::actingAs($this->accountant);

        $response = $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/summary")->assertOk();

        expect($response->json('data.item_count'))->toBe(1);

        $byStatus = collect($response->json('data.by_status'))->keyBy('status');

        expect($byStatus['approved']['count'])->toBe(1)
            ->and($byStatus['submitted']['count'])->toBe(1)
            ->and($byStatus['draft']['count'])->toBe(1)
            ->and($byStatus['rejected']['count'])->toBe(0);
    });

    it('ນັບທຸກສະຖານະໄດ້ດ້ວຍ statuses=all', function () {
        ($this->approved)();
        createExpense($this->project, $this->accountant, ['title' => 'ຮ່າງ']);

        Sanctum::actingAs($this->accountant);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/summary?statuses=all")
            ->assertOk()
            ->assertJsonPath('data.item_count', 2);
    });

    it('ນັບລາຍການທີ່ຍັງບໍ່ມີຫຼັກຖານ', function () {
        ($this->approved)();
        ($this->approved)(['title' => 'ອີກລາຍການ']);

        Sanctum::actingAs($this->accountant);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/summary")
            ->assertOk()
            ->assertJsonPath('data.missing_evidence_count', 2);
    });

    it('ບໍ່ຮົ່ວຍອດຂອງໂຄງການອື່ນ', function () {
        ($this->approved)();

        $otherProject = Project::factory()->create();
        $otherUser = memberOf($otherProject, 'modify');
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/projects/{$otherProject->uuid}/reports/summary")
            ->assertOk()
            ->assertJsonPath('data.item_count', 0)
            ->assertJsonPath('data.total_usd.minor', 0);
    });

    it('ຄົນນອກໂຄງການເບິ່ງລາຍງານບໍ່ໄດ້', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/summary")->assertForbidden();
    });
});

describe('by-category', function () {
    beforeEach(function () {
        ($this->approved)(['category_id' => $this->equipment->id, 'fx_rate_per_usd' => '21760']);
        ($this->approved)(['category_id' => $this->equipment->id, 'fx_rate_per_usd' => '21760']);
        ($this->approved)(['category_id' => $this->labor->id, 'amount' => '5,000,000', 'fx_rate_per_usd' => '21760']);
        ($this->approved)(['fx_rate_per_usd' => '21760']);   // ບໍ່ໄດ້ລະບຸໝວດ

        Sanctum::actingAs($this->accountant);
    });

    it('ຈັດກຸ່ມຕາມໝວດ ຮຽງຈາກຍອດຫຼາຍໄປໜ້ອຍ', function () {
        $data = $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/by-category")
            ->assertOk()
            ->json('data');

        expect($data)->toHaveCount(3)
            ->and($data[0]['label'])->toBe('ອຸປະກອນຕ່າງໆ')
            ->and($data[0]['count'])->toBe(2)
            ->and($data[0]['usd']['minor'])->toBe(114_890);
    });

    it('ສະແດງແຖວທີ່ບໍ່ມີໝວດເປັນກຸ່ມຂອງມັນເອງ ບໍ່ແມ່ນເຊື່ອງ', function () {
        // 36% ຂອງຍອດໃນໄຟລ໌ເດີມຕົກໃສ່ກຸ່ມນີ້ — ຖ້າເຊື່ອງ ລາຍງານຈະຜິດ
        $data = collect($this->getJson("/api/v1/projects/{$this->project->uuid}/reports/by-category")->json('data'));

        $unclassified = $data->firstWhere('label', 'ບໍ່ໄດ້ລະບຸໝວດ');

        expect($unclassified)->not->toBeNull()
            ->and($unclassified['category_id'])->toBeNull()
            ->and($unclassified['color'])->toBe('#94a3b8')
            ->and($unclassified['usd']['minor'])->toBe(57_445);
    });

    it('ຄິດ % ຢູ່ backend ແລະ ລວມກັນໄດ້ 100', function () {
        $data = $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/by-category")->json('data');

        expect(round(array_sum(array_column($data, 'percentage')), 1))->toBe(100.0);
    });

    it('ກັ່ນຕອງຕາມຊ່ວງວັນທີໄດ້', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/by-category?date_from=2027-01-01")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('by-payer', function () {
    it('ຈັດກຸ່ມຕາມຜູ້ຈ່າຍ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id, 'fx_rate_per_usd' => '21760']);
        ($this->approved)(['fx_rate_per_usd' => '21760']);

        Sanctum::actingAs($this->accountant);

        $data = collect($this->getJson("/api/v1/projects/{$this->project->uuid}/reports/by-payer")->json('data'));

        expect($data)->toHaveCount(2)
            ->and($data->firstWhere('label', 'Sinexay')['usd']['minor'])->toBe(57_445)
            ->and($data->firstWhere('label', 'ບໍ່ໄດ້ລະບຸຜູ້ຈ່າຍ'))->not->toBeNull();
    });
});

describe('monthly-trend', function () {
    it('ຈັດກຸ່ມຕາມເດືອນ ພ້ອມຍອດສະສົມ', function () {
        ($this->approved)(['expense_date' => '2026-06-10', 'fx_rate_per_usd' => '21760']);
        ($this->approved)(['expense_date' => '2026-06-20', 'fx_rate_per_usd' => '21760']);
        ($this->approved)(['expense_date' => '2026-08-21', 'fx_rate_per_usd' => '21760']);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/monthly-trend")
            ->assertOk()
            ->json('data');

        expect($data)->toHaveCount(2)
            ->and($data[0]['month'])->toBe('2026-06')
            ->and($data[0]['count'])->toBe(2)
            ->and($data[0]['usd']['minor'])->toBe(114_890)
            ->and($data[0]['cumulative_usd']['minor'])->toBe(114_890)
            ->and($data[1]['month'])->toBe('2026-08')
            ->and($data[1]['cumulative_usd']['minor'])->toBe(172_335);   // ສະສົມ
    });
});

describe('missing-evidence', function () {
    it('ຮຽງລາຍການທີ່ບໍ່ມີຫຼັກຖານຈາກຍອດໃຫຍ່ໄປນ້ອຍ', function () {
        ($this->approved)(['amount' => '5,000,000', 'title' => 'ນ້ອຍ', 'fx_rate_per_usd' => '21760']);
        ($this->approved)(['amount' => '50,000,000', 'title' => 'ໃຫຍ່', 'fx_rate_per_usd' => '21760']);

        Sanctum::actingAs($this->accountant);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/missing-evidence")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'ໃຫຍ່')     // ຄວາມສ່ຽງສູງສຸດຂຶ້ນກ່ອນ
            ->assertJsonPath('data.1.title', 'ນ້ອຍ');
    });

    it('ບໍ່ນັບລາຍການທີ່ມີຫຼັກຖານແລ້ວ', function () {
        $withEvidence = ($this->approved)(['title' => 'ມີໃບໂອນ']);
        DB::table('expenses')->where('id', $withEvidence->id)->update(['attachments_count' => 1]);

        ($this->approved)(['title' => 'ບໍ່ມີໃບໂອນ']);

        Sanctum::actingAs($this->accountant);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/missing-evidence")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ບໍ່ມີໃບໂອນ');
    });
});

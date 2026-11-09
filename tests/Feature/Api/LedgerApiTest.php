<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\ExpenseService;
use App\Services\RevenueService;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->editor = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');

    /** ອະນຸມັດລາຍຈ່າຍ — GL ນັບສະເພາະ approved ຕາມຄ່າຕັ້ງຕົ້ນ */
    $this->approvedExpense = function (array $overrides = []) {
        $expense = createExpense($this->project, $this->editor, $overrides);
        $service = app(ExpenseService::class);
        $service->submit($expense);

        return $service->approve($expense, $this->approver->id);
    };

    $this->approvedRevenue = function (array $overrides = []) {
        $revenue = createRevenue($this->project, $this->editor, $overrides);
        $service = app(RevenueService::class);
        $service->submit($revenue);

        return $service->approve($revenue, $this->approver->id);
    };

    $this->ledger = fn (string $query = '') => $this
        ->getJson("/api/v1/projects/{$this->project->uuid}/ledger{$query}")
        ->assertOk()
        ->json('data');
});

describe('ຍອດລວມ ແລະ ຍອດຄົງເຫຼືອ', function () {
    it('ຍອດຄົງເຫຼືອ = ລາຍຮັບ − ລາຍຈ່າຍ', function () {
        ($this->approvedRevenue)(['amount' => '10000.00']);
        ($this->approvedExpense)(['currency_code' => 'USD', 'amount' => '3500.00']);

        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)()['totals'];

        expect($totals['revenue_usd']['minor'])->toBe(1000000)
            ->and($totals['expense_usd']['minor'])->toBe(350000)
            ->and($totals['balance_usd']['minor'])->toBe(650000)
            ->and($totals['balance_usd']['decimal'])->toBe('6500.00')
            ->and($totals['entry_count'])->toBe(2);
    });

    it('ຈ່າຍເກີນເງິນທີ່ຮັບ → ຍອດຄົງເຫຼືອຕິດລົບ', function () {
        ($this->approvedRevenue)(['amount' => '1000.00']);
        ($this->approvedExpense)(['currency_code' => 'USD', 'amount' => '2500.00']);

        Sanctum::actingAs($this->editor);

        expect(($this->ledger)()['totals']['balance_usd']['minor'])->toBe(-150000);
    });

    it('ລວມຂ້າມສະກຸນເງິນໄດ້ຖືກຕ້ອງ', function () {
        // ຮັບ 21,760,000 ກີບ = USD 1,000 | ຈ່າຍ USD 400
        ($this->approvedRevenue)(['currency_code' => 'LAK', 'amount' => '21,760,000']);
        ($this->approvedExpense)(['currency_code' => 'USD', 'amount' => '400.00']);

        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)()['totals'];

        expect($totals['revenue_usd']['minor'])->toBe(100000)
            ->and($totals['balance_usd']['minor'])->toBe(60000);
    });

    it('ຄືນຍອດເປັນກີບນຳ — ໜ້າຈໍສະແດງເປັນກີບ', function () {
        // ຮັບ 21,760,000 ກີບ | ຈ່າຍ USD 400 = 8,704,000 ກີບ (rate 21,760)
        ($this->approvedRevenue)(['currency_code' => 'LAK', 'amount' => '21,760,000']);
        ($this->approvedExpense)(['currency_code' => 'USD', 'amount' => '400.00']);

        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)()['totals'];

        expect($totals['revenue_lak']['minor'])->toBe(21760000)
            ->and($totals['revenue_lak']['currency'])->toBe('LAK')
            ->and($totals['revenue_lak']['decimals'])->toBe(0)   // ກີບບໍ່ມີຫົວໜ່ວຍຍ່ອຍ
            ->and($totals['expense_lak']['minor'])->toBe(8704000)
            ->and($totals['balance_lak']['minor'])->toBe(13056000);
    });

    it('ໂຄງການເປົ່າ → ທຸກຍອດເປັນສູນ ບໍ່ແມ່ນ null', function () {
        Sanctum::actingAs($this->editor);
        $data = ($this->ledger)();

        expect($data['entries'])->toBe([])
            ->and($data['totals']['revenue_usd']['minor'])->toBe(0)
            ->and($data['totals']['balance_usd']['minor'])->toBe(0)
            ->and($data['totals']['revenue_lak']['minor'])->toBe(0)
            ->and($data['totals']['balance_lak']['minor'])->toBe(0);
    });
});

describe('ແຖວບັນຊີ', function () {
    it('ຮຽງຕາມວັນທີ ແລະ ມີຍອດຄົງເຫຼືອແລ່ນສະສົມ', function () {
        ($this->approvedRevenue)(['received_date' => '2026-01-10', 'amount' => '5000.00']);
        ($this->approvedExpense)(['expense_date' => '2026-02-15', 'currency_code' => 'USD', 'amount' => '1000.00']);
        ($this->approvedExpense)(['expense_date' => '2026-03-20', 'currency_code' => 'USD', 'amount' => '500.00']);

        Sanctum::actingAs($this->editor);
        $entries = ($this->ledger)()['entries'];

        expect($entries)->toHaveCount(3)
            ->and($entries[0]['type'])->toBe('revenue')
            ->and($entries[0]['balance_usd']['minor'])->toBe(500000)   // +5000
            ->and($entries[1]['type'])->toBe('expense')
            ->and($entries[1]['balance_usd']['minor'])->toBe(400000)   // −1000
            ->and($entries[2]['balance_usd']['minor'])->toBe(350000);  // −500
    });

    it('ຍອດຄົງເຫຼືອແລ່ນເປັນກີບກໍສະສົມຄືກັນ', function () {
        // rate 21,760 ກີບ/USD — ຄໍລຳໜ້າຈໍອ່ານຈາກຍອດກີບນີ້ ບໍ່ແມ່ນຄິດເອງຢູ່ browser
        ($this->approvedRevenue)(['received_date' => '2026-01-10', 'currency_code' => 'LAK', 'amount' => '5,000,000']);
        ($this->approvedExpense)(['expense_date' => '2026-02-15', 'currency_code' => 'LAK', 'amount' => '1,200,000']);
        ($this->approvedExpense)(['expense_date' => '2026-03-20', 'currency_code' => 'USD', 'amount' => '100.00']);

        Sanctum::actingAs($this->editor);
        $entries = ($this->ledger)()['entries'];

        expect($entries[0]['balance_lak']['minor'])->toBe(5000000)
            ->and($entries[1]['balance_lak']['minor'])->toBe(3800000)
            ->and($entries[2]['balance_lak']['minor'])->toBe(3800000 - 2176000)
            ->and($entries[2]['amount_lak']['minor'])->toBe(2176000);
    });

    it('ແຕ່ລະແຖວບອກປະເພດໃຫ້ໜ້າຈໍເລືອກສີໄດ້', function () {
        ($this->approvedRevenue)();
        ($this->approvedExpense)();

        Sanctum::actingAs($this->editor);
        $types = array_column(($this->ledger)()['entries'], 'type');

        expect($types)->toContain('revenue')->toContain('expense');
    });

    it('ເກັບຈຳນວນເດີມໃນສະກຸນທີ່ບັນທຶກໄວ້ນຳ', function () {
        ($this->approvedExpense)(['currency_code' => 'LAK', 'amount' => '12,500,000']);

        Sanctum::actingAs($this->editor);
        $entry = ($this->ledger)()['entries'][0];

        expect($entry['amount']['currency'])->toBe('LAK')
            ->and($entry['amount']['minor'])->toBe(12500000)
            ->and($entry['amount']['decimals'])->toBe(0);   // ກີບບໍ່ມີຫົວໜ່ວຍຍ່ອຍ
    });

    it('ລາຍການທີ່ຊື່ຊ້ຳ ຈຳນວນຊ້ຳ ວັນດຽວກັນ ບໍ່ຖືກຕັດຖິ້ມ', function () {
        // ຈ່າຍຄ່າອາຫານ 50 USD ສອງເທື່ອໃນມື້ດຽວ = ສອງແຖວ ບໍ່ແມ່ນແຖວດຽວ.
        // ນີ້ຄືເຫດຜົນທີ່ໃຊ້ UNION ALL ບໍ່ແມ່ນ UNION.
        ($this->approvedExpense)(['currency_code' => 'USD', 'amount' => '50.00', 'title' => 'ຄ່າອາຫານ']);
        ($this->approvedExpense)(['currency_code' => 'USD', 'amount' => '50.00', 'title' => 'ຄ່າອາຫານ']);

        Sanctum::actingAs($this->editor);
        $data = ($this->ledger)();

        expect($data['entries'])->toHaveCount(2)
            ->and($data['totals']['expense_usd']['minor'])->toBe(10000);
    });
});

describe('ການກັ່ນຕອງ', function () {
    it('ນັບສະເພາະ approved ຕາມຄ່າຕັ້ງຕົ້ນ', function () {
        ($this->approvedRevenue)(['amount' => '1000.00']);
        createRevenue($this->project, $this->editor, ['amount' => '9999.00']);   // ຍັງເປັນ draft

        Sanctum::actingAs($this->editor);

        expect(($this->ledger)()['totals']['revenue_usd']['minor'])->toBe(100000);
    });

    it('statuses=all ນັບທຸກສະຖານະ', function () {
        ($this->approvedRevenue)(['amount' => '1000.00']);
        createRevenue($this->project, $this->editor, ['amount' => '500.00']);

        Sanctum::actingAs($this->editor);

        expect(($this->ledger)('?statuses=all')['totals']['revenue_usd']['minor'])->toBe(150000);
    });

    it('ກັ່ນຕອງຕາມຊ່ວງວັນທີ ລວມທັງມື້ສຸດທ້າຍ', function () {
        ($this->approvedRevenue)(['received_date' => '2026-01-10', 'amount' => '1000.00']);
        ($this->approvedRevenue)(['received_date' => '2026-03-31', 'amount' => '2000.00']);
        ($this->approvedRevenue)(['received_date' => '2026-04-01', 'amount' => '4000.00']);

        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)('?date_from=2026-01-01&date_to=2026-03-31')['totals'];

        // ມື້ສຸດທ້າຍຕ້ອງຖືກນັບ — ນີ້ຄືບັນຫາທີ່ DateOnly cast ແກ້ໄວ້
        expect($totals['revenue_usd']['minor'])->toBe(300000)
            ->and($totals['entry_count'])->toBe(2);
    });

    it('ລາຍການທີ່ລຶບແລ້ວບໍ່ຖືກນັບ', function () {
        $revenue = ($this->approvedRevenue)(['amount' => '1000.00']);
        ($this->approvedRevenue)(['amount' => '2000.00']);

        $revenue->delete();

        Sanctum::actingAs($this->editor);

        expect(($this->ledger)()['totals']['revenue_usd']['minor'])->toBe(200000);
    });
});

describe('ງົບປະມານທີ່ຍັງເຫຼືອ', function () {
    it('ງົບ − ລາຍຈ່າຍ = ຍັງໃຊ້ໄດ້ອີກ (ຄົນລະຄຳຖາມກັບຍອດຄົງເຫຼືອ)', function () {
        $this->project->update(['budget_currency_code' => 'USD', 'budget_amount_minor' => 1000000]);
        ($this->approvedExpense)(['currency_code' => 'USD', 'amount' => '2500.00']);

        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)()['totals'];

        expect($totals['budget_usd']['minor'])->toBe(1000000)
            ->and($totals['budget_remaining_usd']['minor'])->toBe(750000);
    });

    it('ງົບເປັນກີບ → ຄິດຍອດທີ່ຍັງເຫຼືອເປັນກີບ', function () {
        $this->project->update(['budget_currency_code' => 'LAK', 'budget_amount_minor' => 50_000_000]);
        ($this->approvedExpense)(['currency_code' => 'LAK', 'amount' => '12,500,000']);

        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)()['totals'];

        expect($totals['budget_lak']['minor'])->toBe(50_000_000)
            ->and($totals['budget_remaining_lak']['minor'])->toBe(37_500_000)
            ->and($totals['budget_currency'])->toBe('LAK');
    });

    it('ງົບເປັນສະກຸນອື່ນ → null ພ້ອມບອກສະກຸນ ບໍ່ໃຫ້ອ່ານວ່າຍັງບໍ່ໄດ້ຕັ້ງງົບ', function () {
        // ງົບບໍ່ມີ rate snapshot ຂອງຕົນເອງ ຈຶ່ງແປງບໍ່ໄດ້ແບບ audit-safe —
        // ແຕ່ໜ້າຈໍຕ້ອງແຍກອອກຈາກ "ຍັງບໍ່ໄດ້ຕັ້ງງົບ" ບໍ່ດັ່ງນັ້ນຈະບອກຜູ້ໃຊ້ຜິດ
        $this->project->update(['budget_currency_code' => 'USD', 'budget_amount_minor' => 1000000]);

        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)()['totals'];

        expect($totals['budget_lak'])->toBeNull()
            ->and($totals['budget_remaining_lak'])->toBeNull()
            ->and($totals['budget_currency'])->toBe('USD');
    });

    it('ໂຄງການທີ່ບໍ່ໄດ້ຕັ້ງງົບ → null ບໍ່ແມ່ນສູນ', function () {
        Sanctum::actingAs($this->editor);
        $totals = ($this->ledger)()['totals'];

        // ສູນຈະຖືກອ່ານວ່າ "ງົບໝົດແລ້ວ" ເຊິ່ງຜິດ — null ບອກວ່າ "ຍັງບໍ່ໄດ້ຕັ້ງງົບ"
        expect($totals['budget_usd'])->toBeNull()
            ->and($totals['budget_remaining_usd'])->toBeNull()
            ->and($totals['budget_lak'])->toBeNull()
            ->and($totals['budget_currency'])->toBeNull();
    });
});

describe('ສິດເຂົ້າເຖິງ', function () {
    it('ຄົນນອກໂຄງການເປີດ GL ບໍ່ໄດ້', function () {
        $outsider = memberOf(Project::factory()->create(), 'full_access');

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/ledger")->assertForbidden();
    });

    it('read_only ເປີດ GL ໄດ້', function () {
        $reader = memberOf($this->project, 'read_only');

        Sanctum::actingAs($reader);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/ledger")->assertOk();
    });
});

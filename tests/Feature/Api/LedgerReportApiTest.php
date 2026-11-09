<?php

declare(strict_types=1);

use App\Models\Payer;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\RevenueService;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create(['code' => 'SOLAR-3MW']);
    $this->accountant = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');

    $this->expenses = app(ExpenseService::class);
    $this->revenues = app(RevenueService::class);

    $this->sinexay = Payer::factory()->create(['name' => 'Sinexay']);
    $this->sss = Payer::factory()->create(['name' => 'SSS']);

    $this->approvedRevenue = function (array $overrides = []) {
        $revenue = createRevenue($this->project, $this->accountant, $overrides);
        $this->revenues->submit($revenue);

        return $this->revenues->approve($revenue, $this->approver->id);
    };

    $this->approvedExpense = function (array $overrides = []) {
        $expense = createExpense($this->project, $this->accountant, [
            'fx_rate_per_usd' => '21760',
            ...$overrides,
        ]);
        $this->expenses->submit($expense);

        return $this->expenses->approve($expense, $this->approver->id);
    };

    $this->url = fn (string $suffix = ''): string => "/api/v1/projects/{$this->project->uuid}/reports/ledger{$suffix}";
});

describe('ລາຍງານລາຍຮັບ-ລາຍຈ່າຍ (JSON)', function () {
    it('ຄືນສ່ວນລະອຽດ ສ່ວນສະຫຼຸບຕໍ່ເດືອນ ແລະ ຍອດລວມ ໃນ request ດຽວ', function () {
        ($this->approvedRevenue)(['received_date' => '2026-06-01', 'amount' => '1000.00']);
        ($this->approvedExpense)(['expense_date' => '2026-06-15', 'amount' => '5,000,000']);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)())->assertOk()->json('data');

        expect($data['entries'])->toHaveCount(2)
            ->and($data['monthly'])->toHaveCount(1)
            ->and($data['totals']['entry_count'])->toBe(2)
            ->and($data['row_count'])->toBe(2);
    });

    it('ສ່ວນສະຫຼຸບຕໍ່ເດືອນກົງກັບຍອດຄົງເຫຼືອຂອງແຖວສຸດທ້າຍ', function () {
        // ມິຖຸນາ: ຮັບ 1,000 USD, ຈ່າຍ 5,000,000 ກີບ (= 229.78 USD) → ສຸດທິ 770.22
        ($this->approvedRevenue)(['received_date' => '2026-06-01', 'amount' => '1000.00']);
        ($this->approvedExpense)(['expense_date' => '2026-06-15', 'amount' => '5,000,000']);
        // ສິງຫາ: ຮັບອີກ 500 USD → ຍອດສະສົມ 1,270.22
        ($this->approvedRevenue)(['received_date' => '2026-08-10', 'amount' => '500.00']);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)())->assertOk()->json('data');
        $entries = $data['entries'];
        $monthly = $data['monthly'];

        expect($monthly)->toHaveCount(2)
            ->and($monthly[0]['month'])->toBe('2026-06')
            ->and($monthly[0]['revenue_usd']['minor'])->toBe(100_000)
            ->and($monthly[0]['expense_usd']['minor'])->toBe(22_978)
            ->and($monthly[0]['net_usd']['minor'])->toBe(77_022)
            ->and($monthly[1]['month'])->toBe('2026-08')
            // ຍອດຄົງເຫຼືອທ້າຍເດືອນສຸດທ້າຍ = ຍອດຄົງເຫຼືອຂອງແຖວສຸດທ້າຍ = ຍອດລວມ
            ->and($monthly[1]['balance_usd']['minor'])->toBe(127_022)
            ->and(end($entries)['balance_usd']['minor'])->toBe(127_022)
            ->and($data['totals']['balance_usd']['minor'])->toBe(127_022);
    });

    it('ຄົ້ນຫາຂ້າມທັງລາຍຮັບ ແລະ ລາຍຈ່າຍ', function () {
        ($this->approvedRevenue)(['title' => 'ເງິນໂອນຄ່າແຜງໂຊລາ']);
        ($this->approvedExpense)(['title' => 'ຄ່າແຜງໂຊລາ 500W']);
        ($this->approvedExpense)(['title' => 'ຄ່າແຮງງານ']);

        Sanctum::actingAs($this->accountant);

        $entries = $this->getJson(($this->url)('?search=ແຜງໂຊລາ'))->assertOk()->json('data.entries');

        expect($entries)->toHaveCount(2)
            ->and(collect($entries)->pluck('type')->sort()->values()->all())
            ->toBe(['expense', 'revenue']);
    });

    it('ກັ່ນຕອງຕາມຜູ້ຈ່າຍຂ້າມທັງສອງຝັ່ງ', function () {
        ($this->approvedRevenue)(['payer_id' => $this->sinexay->id]);
        ($this->approvedExpense)(['payer_id' => $this->sinexay->id]);
        ($this->approvedExpense)(['payer_id' => $this->sss->id]);

        Sanctum::actingAs($this->accountant);

        expect($this->getJson(($this->url)("?payer_id={$this->sinexay->id}"))->json('data.entries'))
            ->toHaveCount(2);
    });

    it('ນັບສະເພາະທີ່ອະນຸມັດແລ້ວ ຈົນກວ່າຈະຂໍ statuses=all', function () {
        ($this->approvedRevenue)();
        createExpense($this->project, $this->accountant, ['title' => 'ຮ່າງ']);

        Sanctum::actingAs($this->accountant);

        expect($this->getJson(($this->url)())->json('data.entries'))->toHaveCount(1);
        expect($this->getJson(($this->url)('?statuses=all'))->json('data.entries'))->toHaveCount(2);
    });

    it('ເກີນເພດານ = ບໍ່ສະແດງແຖວ ແຕ່ຍັງບອກຈຳນວນ', function () {
        ($this->approvedRevenue)();
        ($this->approvedExpense)();
        config()->set('pdf.max_rows', 1);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)())->assertOk()->json('data');

        expect($data['entries'])->toBe([])
            ->and($data['monthly'])->toBe([])
            ->and($data['totals'])->toBeNull()
            ->and($data['row_count'])->toBe(2)
            ->and($data['max_rows'])->toBe(1);
    });

    it('ຄົນນອກໂຄງການເປີດລາຍງານບໍ່ໄດ້', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(($this->url)())->assertForbidden();
    });
});

describe('ພິມ PDF ບັນຊີແຍກປະເພດ', function () {
    beforeEach(function () {
        // ໜ້າຈໍບໍ່ໄດ້ປະກອບ URL ຂອງ PDF ເອງ — server ເຊັນມາໃຫ້ໃນ `pdf_url`.
        // ພຶດຕິກຳຂອງລາຍເຊັນເອງຢູ່ໃນ ReportPdfLinkTest
        $this->pdfUrl = function (string $query = ''): ?string {
            Sanctum::actingAs($this->accountant);

            return $this->getJson(($this->url)($query))->assertOk()->json('data.pdf_url');
        };
    });

    it('ຄືນໄຟລ໌ PDF ຈິງທີ່ເປີດເບິ່ງໃນແທັບໄດ້ ບໍ່ແມ່ນບັງຄັບດາວໂຫຼດ', function () {
        ($this->approvedRevenue)(['payer_id' => $this->sinexay->id]);
        ($this->approvedExpense)();

        $response = $this->get(($this->pdfUrl)())->assertOk();

        expect($response->headers->get('content-type'))->toBe('application/pdf')
            // inline = ຕົວອ່ານ PDF ຂອງ browser ເປີດໃຫ້ເບິ່ງກ່ອນ ບໍ່ແມ່ນໂຫຼດລົງເຄື່ອງທັນທີ
            ->and($response->headers->get('content-disposition'))->toStartWith('inline;')
            ->and($response->headers->get('content-disposition'))->toContain('ledger-SOLAR-3MW-')
            ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
    });

    it('ຝັງຟອນລາວເຂົ້າໄຟລ໌', function () {
        ($this->approvedRevenue)();

        expect($this->get(($this->pdfUrl)())->assertOk()->getContent())->toContain('Phetsarath');
    });

    it('ບໍ່ອອກລິ້ງໃຫ້ເມື່ອແຖວເກີນເພດານ', function () {
        ($this->approvedRevenue)();
        ($this->approvedExpense)();
        config()->set('pdf.max_rows', 1);

        expect(($this->pdfUrl)())->toBeNull();
    });

    it('ຄົນນອກໂຄງການບໍ່ໄດ້ແມ່ນແຕ່ລິ້ງ', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(($this->url)())->assertForbidden();
    });
});

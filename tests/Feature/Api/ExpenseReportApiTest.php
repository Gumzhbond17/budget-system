<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payer;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create(['code' => 'SOLAR-3MW']);
    $this->accountant = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');
    $this->service = app(ExpenseService::class);

    $this->equipment = ExpenseCategory::factory()->create(['code' => 'EQUIPMENT', 'name_lo' => 'ອຸປະກອນຕ່າງໆ']);
    $this->sinexay = Payer::factory()->create(['name' => 'Sinexay']);
    $this->sss = Payer::factory()->create(['name' => 'SSS']);

    $this->approved = function (array $overrides = []): Expense {
        $expense = createExpense($this->project, $this->accountant, [
            'fx_rate_per_usd' => '21760',
            ...$overrides,
        ]);
        $this->service->submit($expense);

        return $this->service->approve($expense, $this->approver->id);
    };

    $this->url = fn (string $suffix = ''): string => "/api/v1/projects/{$this->project->uuid}/reports/expenses{$suffix}";
});

describe('ລາຍງານລາຍຈ່າຍ (JSON)', function () {
    it('ຄືນຍອດລວມ ສະຫຼຸບຕາມຜູ້ຈ່າຍ ແລະ ທຸກແຖວ ໃນ request ດຽວ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);
        ($this->approved)(['payer_id' => $this->sss->id, 'amount' => '5,000,000']);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)())->assertOk()->json('data');

        expect($data['summary']['item_count'])->toBe(2)
            ->and($data['summary']['total_usd']['minor'])->toBe(57_445 + 22_978)
            ->and($data['by_payer'])->toHaveCount(2)
            ->and($data['expenses'])->toHaveCount(2)
            ->and($data['row_count'])->toBe(2);
    });

    it('ແຖວທີ່ຄືນມາມີຜູ້ຈ່າຍ ແລະ ໝວດ ຕິດມານຳ (ບໍ່ຕ້ອງຍິງເພີ່ມ)', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id, 'category_id' => $this->equipment->id]);

        Sanctum::actingAs($this->accountant);

        $this->getJson(($this->url)())
            ->assertOk()
            ->assertJsonPath('data.expenses.0.payer.name', 'Sinexay')
            ->assertJsonPath('data.expenses.0.category.name_lo', 'ອຸປະກອນຕ່າງໆ');
    });

    it('ຮຽງເກົ່າໄປໃໝ່ — ໃບທີ່ພິມອອກມາອ່ານຕາມລຳດັບເວລາ', function () {
        ($this->approved)(['expense_date' => '2026-08-21', 'title' => 'ຫຼັງ']);
        ($this->approved)(['expense_date' => '2026-06-10', 'title' => 'ກ່ອນ']);

        Sanctum::actingAs($this->accountant);

        $this->getJson(($this->url)())
            ->assertOk()
            ->assertJsonPath('data.expenses.0.title', 'ກ່ອນ')
            ->assertJsonPath('data.expenses.1.title', 'ຫຼັງ');
    });

    it('ກັ່ນຕອງຕາມຜູ້ຈ່າຍ — ຍອດລວມ ແລະ ແຖວ ຫຼຸດລົງພ້ອມກັນ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);
        ($this->approved)(['payer_id' => $this->sss->id]);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)("?payer_id={$this->sinexay->id}"))->assertOk()->json('data');

        expect($data['expenses'])->toHaveCount(1)
            ->and($data['summary']['item_count'])->toBe(1)
            ->and($data['by_payer'])->toHaveCount(1)
            ->and($data['by_payer'][0]['label'])->toBe('Sinexay');
    });

    it('ຄົ້ນຫາຈາກ ຫົວຂໍ້ ຄຳອະທິບາຍ ຫຼື ເລກທີ່ໃບສຳຄັນ', function () {
        ($this->approved)(['title' => 'ຄ່າເສົາໄຟຟ້າ']);
        ($this->approved)(['title' => 'ຄ່າແຮງງານ', 'description' => 'ຈ້າງທີມຕິດຕັ້ງແຜງ']);

        Sanctum::actingAs($this->accountant);

        expect($this->getJson(($this->url)('?search=ເສົາ'))->json('data.expenses'))->toHaveCount(1);
        expect($this->getJson(($this->url)('?search=ຕິດຕັ້ງ'))->json('data.expenses'))->toHaveCount(1);
        expect($this->getJson(($this->url)('?search=ບໍ່ມີຄຳນີ້'))->json('data.expenses'))->toHaveCount(0);
    });

    it('ຄຳຄົ້ນຫາທີ່ມີແຕ່ຊ່ອງຫວ່າງ ບໍ່ຖືວ່າເປັນການຄົ້ນຫາ', function () {
        ($this->approved)();

        Sanctum::actingAs($this->accountant);

        // LIKE '% %' ຈະຕັດແຖວອອກໂດຍທີ່ຜູ້ໃຊ້ເບິ່ງບໍ່ອອກວ່າພິມຫຍັງໄວ້
        expect($this->getJson(($this->url)('?search=%20%20'))->json('data.expenses'))->toHaveCount(1);
    });

    it('ຄົ້ນຫາ ແລະ ຜູ້ຈ່າຍ ໃຊ້ຮ່ວມກັນໄດ້', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id, 'title' => 'ຄ່າເສົາໄຟຟ້າ']);
        ($this->approved)(['payer_id' => $this->sss->id, 'title' => 'ຄ່າເສົາໄຟຟ້າ']);
        ($this->approved)(['payer_id' => $this->sinexay->id, 'title' => 'ຄ່າແຮງງານ']);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)("?payer_id={$this->sinexay->id}&search=ເສົາ"))->assertOk()->json('data');

        expect($data['expenses'])->toHaveCount(1)
            ->and($data['expenses'][0]['title'])->toBe('ຄ່າເສົາໄຟຟ້າ');
    });

    it('ນັບສະເພາະທີ່ອະນຸມັດແລ້ວ ຈົນກວ່າຈະຂໍ statuses=all', function () {
        ($this->approved)();
        createExpense($this->project, $this->accountant, ['title' => 'ຮ່າງ']);

        Sanctum::actingAs($this->accountant);

        expect($this->getJson(($this->url)())->json('data.expenses'))->toHaveCount(1);
        expect($this->getJson(($this->url)('?statuses=all'))->json('data.expenses'))->toHaveCount(2);
    });

    it('ຄົນນອກໂຄງການເປີດລາຍງານບໍ່ໄດ້', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(($this->url)())->assertForbidden();
    });
});

describe('ພິມ PDF', function () {
    beforeEach(function () {
        // ໜ້າຈໍບໍ່ໄດ້ປະກອບ URL ຂອງ PDF ເອງ — server ເຊັນມາໃຫ້ໃນ `pdf_url`
        $this->pdfUrl = function (string $query = ''): ?string {
            Sanctum::actingAs($this->accountant);

            return $this->getJson(($this->url)($query))->assertOk()->json('data.pdf_url');
        };
    });

    it('ຄືນໄຟລ໌ PDF ຈິງທີ່ເປີດເບິ່ງໃນແທັບໄດ້ ບໍ່ແມ່ນບັງຄັບດາວໂຫຼດ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id, 'category_id' => $this->equipment->id]);

        $response = $this->get(($this->pdfUrl)())->assertOk();

        expect($response->headers->get('content-type'))->toBe('application/pdf')
            // inline = ຕົວອ່ານ PDF ຂອງ browser ເປີດໃຫ້ເບິ່ງ.
            // attachment ຈະບັງຄັບໂຫຼດລົງເຄື່ອງທັນທີ ໂດຍທີ່ຜູ້ໃຊ້ຍັງບໍ່ທັນເຫັນ
            ->and($response->headers->get('content-disposition'))->toStartWith('inline;')
            // ຊື່ໄຟລ໌ຍັງຕ້ອງມີ — ເປັນຊື່ຕັ້ງຕົ້ນຕອນກົດບັນທຶກຈາກຕົວອ່ານ
            ->and($response->headers->get('content-disposition'))->toContain('expenses-SOLAR-3MW-')
            // %PDF- ຄື magic bytes — ພິສູດວ່າ mPDF ສ້າງໄຟລ໌ໄດ້ຈິງ ບໍ່ແມ່ນຄືນ HTML
            ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
    });

    it('ຝັງຟອນລາວເຂົ້າໄຟລ໌ — ບໍ່ດັ່ງນັ້ນຈະອອກເປັນ □□□ ຢູ່ເຄື່ອງທີ່ບໍ່ມີຟອນ', function () {
        ($this->approved)();

        expect($this->get(($this->pdfUrl)())->assertOk()->getContent())->toContain('Phetsarath');
    });

    it('ໃຊ້ຕົວກັ່ນຕອງອັນດຽວກັນກັບໜ້າຈໍ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);
        ($this->approved)(['payer_id' => $this->sss->id]);

        $url = ($this->pdfUrl)("?payer_id={$this->sinexay->id}");

        expect($url)->toContain("payer_id={$this->sinexay->id}");

        // ໜຶ່ງແຖວ ບໍ່ແມ່ນສອງ — ຕົວກັ່ນຕອງທີ່ໜ້າຈໍໃຊ້ ຖືກເຊັນຕິດມາໃນ URL
        expect(substr($this->get($url)->assertOk()->getContent(), 0, 5))->toBe('%PDF-');
    });

    it('ບໍ່ອອກລິ້ງໃຫ້ເມື່ອບໍ່ມີແຖວ — ປຸ່ມພິມຈຶ່ງບໍ່ຂຶ້ນ', function () {
        ($this->approved)();

        expect(($this->pdfUrl)('?date_from=2027-01-01'))->toBeNull();
    });

    it('ບໍ່ອອກລິ້ງໃຫ້ເມື່ອແຖວເກີນເພດານ', function () {
        ($this->approved)();
        ($this->approved)();
        config()->set('pdf.max_rows', 1);

        expect(($this->pdfUrl)())->toBeNull();
    });

    it('ຍັງປະຕິເສດຢູ່ຝັ່ງ server ຖ້າຂໍ້ມູນເພີ່ມຂຶ້ນຫຼັງອອກລິ້ງໄປແລ້ວ', function () {
        ($this->approved)();

        $url = ($this->pdfUrl)();

        // ລິ້ງໃຊ້ໄດ້ 30 ນາທີ — ໃນລະຫວ່າງນັ້ນຂໍ້ມູນອາດເກີນເພດານໄປແລ້ວ
        config()->set('pdf.max_rows', 0);

        $message = $this->getJson($url)->assertStatus(422)->json('message');

        // ຂໍ້ຄວາມຕ້ອງບອກທາງແກ້ ບໍ່ແມ່ນແຕ່ "ຜິດພາດ" — ຜູ້ໃຊ້ແກ້ໄດ້ເອງ
        expect($message)->toContain('ແຄບຊ່ວງວັນທີ');
    });

    it('ໜ້າຈໍກໍບໍ່ສະແດງແຖວເມື່ອເກີນເພດານ — ຫ້າມມີກໍລະນີທີ່ເຫັນໄດ້ແຕ່ພິມບໍ່ໄດ້', function () {
        ($this->approved)();
        ($this->approved)();
        config()->set('pdf.max_rows', 1);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)())->assertOk()->json('data');

        expect($data['expenses'])->toBe([])
            ->and($data['row_count'])->toBe(2)
            ->and($data['max_rows'])->toBe(1)
            ->and($data['pdf_url'])->toBeNull();
    });

    it('ຄົນນອກໂຄງການບໍ່ໄດ້ແມ່ນແຕ່ລິ້ງ', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(($this->url)())->assertForbidden();
    });
});

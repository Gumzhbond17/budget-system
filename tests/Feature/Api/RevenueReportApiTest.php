<?php

declare(strict_types=1);

use App\Models\Payer;
use App\Models\Project;
use App\Models\Revenue;
use App\Models\User;
use App\Services\RevenueService;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create(['code' => 'SOLAR-3MW']);
    $this->accountant = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');
    $this->service = app(RevenueService::class);

    $this->sinexay = Payer::factory()->create(['name' => 'Sinexay']);
    $this->sss = Payer::factory()->create(['name' => 'SSS']);

    /** ສ້າງລາຍຮັບແລ້ວອະນຸມັດເລີຍ — ລາຍງານນັບສະເພາະ approved ຕາມຄ່າຕັ້ງຕົ້ນ */
    $this->approved = function (array $overrides = []): Revenue {
        $revenue = createRevenue($this->project, $this->accountant, $overrides);
        $this->service->submit($revenue);

        return $this->service->approve($revenue, $this->approver->id);
    };

    $this->url = fn (string $suffix = ''): string => "/api/v1/projects/{$this->project->uuid}/reports/revenues{$suffix}";
});

describe('ລາຍງານລາຍຮັບ (JSON)', function () {
    it('ຄືນຍອດລວມ ສະຫຼຸບຕາມແຫຼ່ງທຶນ ແລະ ທຸກແຖວ ໃນ request ດຽວ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);                          // USD 1,000
        ($this->approved)(['payer_id' => $this->sss->id, 'amount' => '2500.00']);       // USD 2,500

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)())->assertOk()->json('data');

        expect($data['summary']['item_count'])->toBe(2)
            ->and($data['summary']['total_usd']['minor'])->toBe(350_000)
            ->and($data['by_payer'])->toHaveCount(2)
            ->and($data['revenues'])->toHaveCount(2)
            ->and($data['row_count'])->toBe(2);
    });

    it('ແຖວທີ່ຄືນມາມີແຫຼ່ງທຶນຕິດມານຳ (ບໍ່ຕ້ອງຍິງເພີ່ມ)', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);

        Sanctum::actingAs($this->accountant);

        $this->getJson(($this->url)())
            ->assertOk()
            ->assertJsonPath('data.revenues.0.payer.name', 'Sinexay');
    });

    it('ຮຽງເກົ່າໄປໃໝ່ — ໃບທີ່ພິມອອກມາອ່ານຕາມລຳດັບເວລາ', function () {
        ($this->approved)(['received_date' => '2026-08-21', 'title' => 'ຫຼັງ']);
        ($this->approved)(['received_date' => '2026-06-10', 'title' => 'ກ່ອນ']);

        Sanctum::actingAs($this->accountant);

        $this->getJson(($this->url)())
            ->assertOk()
            ->assertJsonPath('data.revenues.0.title', 'ກ່ອນ')
            ->assertJsonPath('data.revenues.1.title', 'ຫຼັງ');
    });

    it('ກັ່ນຕອງຕາມແຫຼ່ງທຶນ — ຍອດລວມ ແລະ ແຖວ ຫຼຸດລົງພ້ອມກັນ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);
        ($this->approved)(['payer_id' => $this->sss->id]);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)("?payer_id={$this->sinexay->id}"))->assertOk()->json('data');

        expect($data['revenues'])->toHaveCount(1)
            ->and($data['summary']['item_count'])->toBe(1)
            ->and($data['by_payer'])->toHaveCount(1)
            ->and($data['by_payer'][0]['label'])->toBe('Sinexay');
    });

    it('ຄົ້ນຫາຈາກ ຫົວຂໍ້ ເລກອ້າງອີງ ຫຼື ເລກທີ່ໃບສຳຄັນ', function () {
        ($this->approved)(['title' => 'ເງິນໂອນຈາກຫຸ້ນສ່ວນ']);
        ($this->approved)(['title' => 'ເງິນກູ້ທະນາຄານ', 'reference' => 'BCEL-99881']);

        Sanctum::actingAs($this->accountant);

        expect($this->getJson(($this->url)('?search=ຫຸ້ນສ່ວນ'))->json('data.revenues'))->toHaveCount(1);
        expect($this->getJson(($this->url)('?search=BCEL-99881'))->json('data.revenues'))->toHaveCount(1);
        expect($this->getJson(($this->url)('?search=ບໍ່ມີຄຳນີ້'))->json('data.revenues'))->toHaveCount(0);
    });

    it('ຄົ້ນຫາ ແລະ ແຫຼ່ງທຶນ ໃຊ້ຮ່ວມກັນໄດ້', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id, 'title' => 'ເງິນໂອນງວດ 1']);
        ($this->approved)(['payer_id' => $this->sss->id, 'title' => 'ເງິນໂອນງວດ 1']);
        ($this->approved)(['payer_id' => $this->sinexay->id, 'title' => 'ເງິນກູ້ທະນາຄານ']);

        Sanctum::actingAs($this->accountant);

        $data = $this->getJson(($this->url)("?payer_id={$this->sinexay->id}&search=ງວດ"))->assertOk()->json('data');

        expect($data['revenues'])->toHaveCount(1)
            ->and($data['revenues'][0]['title'])->toBe('ເງິນໂອນງວດ 1');
    });

    it('ແຖວທີ່ບໍ່ລະບຸແຫຼ່ງທຶນປາກົດເປັນກຸ່ມຂອງມັນເອງ ບໍ່ແມ່ນເຊື່ອງ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);
        ($this->approved)();   // ບໍ່ໄດ້ລະບຸແຫຼ່ງທຶນ

        Sanctum::actingAs($this->accountant);

        $byPayer = collect($this->getJson(($this->url)())->json('data.by_payer'));

        expect($byPayer)->toHaveCount(2)
            ->and($byPayer->firstWhere('label', 'ບໍ່ໄດ້ລະບຸແຫຼ່ງທຶນ'))->not->toBeNull();
    });

    it('ນັບສະເພາະທີ່ອະນຸມັດແລ້ວ ຈົນກວ່າຈະຂໍ statuses=all', function () {
        ($this->approved)();
        createRevenue($this->project, $this->accountant, ['title' => 'ຮ່າງ']);

        Sanctum::actingAs($this->accountant);

        expect($this->getJson(($this->url)())->json('data.revenues'))->toHaveCount(1);
        expect($this->getJson(($this->url)('?statuses=all'))->json('data.revenues'))->toHaveCount(2);
    });

    it('ຄົນນອກໂຄງການເປີດລາຍງານບໍ່ໄດ້', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(($this->url)())->assertForbidden();
    });
});

describe('ພິມ PDF ລາຍຮັບ', function () {
    beforeEach(function () {
        // ໜ້າຈໍບໍ່ໄດ້ປະກອບ URL ຂອງ PDF ເອງ — server ເຊັນມາໃຫ້ໃນ `pdf_url`.
        // ພຶດຕິກຳຂອງລາຍເຊັນເອງຢູ່ໃນ ReportPdfLinkTest
        $this->pdfUrl = function (string $query = ''): ?string {
            Sanctum::actingAs($this->accountant);

            return $this->getJson(($this->url)($query))->assertOk()->json('data.pdf_url');
        };
    });

    it('ຄືນໄຟລ໌ PDF ຈິງທີ່ເປີດເບິ່ງໃນແທັບໄດ້ ບໍ່ແມ່ນບັງຄັບດາວໂຫຼດ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);

        $response = $this->get(($this->pdfUrl)())->assertOk();

        expect($response->headers->get('content-type'))->toBe('application/pdf')
            // inline = ຕົວອ່ານ PDF ຂອງ browser ເປີດໃຫ້ເບິ່ງກ່ອນ ບໍ່ແມ່ນໂຫຼດລົງເຄື່ອງທັນທີ
            ->and($response->headers->get('content-disposition'))->toStartWith('inline;')
            ->and($response->headers->get('content-disposition'))->toContain('revenues-SOLAR-3MW-')
            // %PDF- ຄື magic bytes — ພິສູດວ່າ mPDF ສ້າງໄຟລ໌ໄດ້ຈິງ ບໍ່ແມ່ນຄືນ HTML
            ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
    });

    it('ຝັງຟອນລາວເຂົ້າໄຟລ໌ — ບໍ່ດັ່ງນັ້ນຈະອອກເປັນ □□□ ຢູ່ເຄື່ອງທີ່ບໍ່ມີຟອນ', function () {
        ($this->approved)();

        expect($this->get(($this->pdfUrl)())->assertOk()->getContent())->toContain('Phetsarath');
    });

    it('ບໍ່ອອກລິ້ງໃຫ້ເມື່ອແຖວເກີນເພດານ', function () {
        ($this->approved)();
        ($this->approved)();
        config()->set('pdf.max_rows', 1);

        expect(($this->pdfUrl)())->toBeNull();
    });

    it('ຄົນນອກໂຄງການບໍ່ໄດ້ແມ່ນແຕ່ລິ້ງ', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(($this->url)())->assertForbidden();
    });
});

/**
 * endpoint ນ້ຳໜັກເບົາທີ່ໜ້າຫຼັກໃຊ້ແຕ້ມວົງກົມ "ລາຍຮັບຕາມແຫຼ່ງທຶນ".
 *
 * ແຍກຈາກ `reports/revenues` ເພາະອັນນັ້ນດຶງທຸກແຖວ + ເຊັນ URL ຂອງ PDF ນຳ
 * ເຊິ່ງໜ້າຫຼັກບໍ່ໄດ້ໃຊ້ ແລະ ມັນໂຫຼດຄືນອັດຕະໂນມັດເລື້ອຍໆ.
 */
describe('reports/revenue-by-payer', function () {
    beforeEach(function () {
        $this->donutUrl = "/api/v1/projects/{$this->project->uuid}/reports/revenue-by-payer";
    });

    it('ຈັດກຸ່ມຕາມແຫຼ່ງທຶນ ພ້ອມສັດສ່ວນທີ່ຄິດຢູ່ backend', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id, 'amount' => '3000.00']);
        ($this->approved)(['payer_id' => $this->sss->id, 'amount' => '1000.00']);

        Sanctum::actingAs($this->accountant);

        $rows = collect($this->getJson($this->donutUrl)->assertOk()->json('data'));

        expect($rows)->toHaveCount(2)
            // ຮຽງຈາກຫຼາຍໄປໜ້ອຍ — ວົງກົມຕັດເອົາ 6 ອັນທຳອິດ ຈຶ່ງຕ້ອງເປັນອັນໃຫຍ່
            ->and($rows->first()['label'])->toBe('Sinexay')
            // toEqual ບໍ່ແມ່ນ toBe: JSON ຫຼຸດ 75.0 ລົງເປັນ 75 (int) —
            // ຢູ່ຝັ່ງ JS ສອງຄ່ານີ້ຄືກັນ ຈຶ່ງບໍ່ຕ້ອງແຍກຊະນິດຢູ່ນີ້
            ->and($rows->first()['percentage'])->toEqual(75.0)
            ->and($rows->last()['percentage'])->toEqual(25.0);
    });

    it('ນັບສະເພາະລາຍຮັບທີ່ອະນຸມັດແລ້ວ', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);
        createRevenue($this->project, $this->accountant, ['payer_id' => $this->sss->id]);

        Sanctum::actingAs($this->accountant);

        expect($this->getJson($this->donutUrl)->assertOk()->json('data'))->toHaveCount(1);
    });

    it('ຄົນນອກໂຄງການເປີດບໍ່ໄດ້', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson($this->donutUrl)->assertForbidden();
    });

    it('ຜູ້ອ່ານຢ່າງດຽວທີ່ເປັນສະມາຊິກເປີດໄດ້', function () {
        ($this->approved)(['payer_id' => $this->sinexay->id]);

        Sanctum::actingAs(memberOf($this->project, 'read_only'));

        $this->getJson($this->donutUrl)->assertOk();
    });
});

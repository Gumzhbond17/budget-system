<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\Payer;
use App\Models\Project;
use App\Models\Revenue;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->owner = memberOf($this->project, 'full_access');
    $this->editor = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');
    $this->reader = memberOf($this->project, 'read_only');
});

describe('ສ້າງລາຍຮັບ', function () {
    it('modify ສ້າງໄດ້ ແລະ ໄດ້ເລກ REV- ອັດຕະໂນມັດ', function () {
        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ເງິນທຶນຈາກ SSS',
            'currency_code' => 'USD',
            'amount' => '10,000.00',
        ])->assertCreated()
            ->assertJsonPath('data.doc_no', 'REV-2026-0001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.amount.minor', 1000000);
    });

    it('ເລກລາຍຮັບແຍກລຳດັບຈາກເລກລາຍຈ່າຍ', function () {
        // ຖ້າໃຊ້ລຳດັບຮ່ວມກັນ ນັກບັນຊີຈະເຫັນເລກຂາດຊ່ວງແລ້ວນຶກວ່າຂໍ້ມູນຫາຍ
        createExpense($this->project, $this->editor);
        $revenue = createRevenue($this->project, $this->editor);

        expect($revenue->doc_no)->toBe('REV-2026-0001');
    });

    it('ແປງ ກີບ ເປັນ USD ດ້ວຍ rate ທີ່ snapshot ໄວ້', function () {
        Sanctum::actingAs($this->editor);

        // 21,760,000 ກີບ ÷ 21,760 = USD 1,000.00
        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ເງິນສົດຮັບ',
            'currency_code' => 'LAK',
            'amount' => '21,760,000',
        ])->assertCreated()
            ->assertJsonPath('data.amount_usd.minor', 100000)
            ->assertJsonPath('data.amount_lak.minor', 21760000);
    });

    it('ຮັບ rate ທີ່ມີເຄື່ອງໝາຍຈຸດຫຼັກພັນຈາກຟອມ ("22,239")', function () {
        // NumericField ຜູກ v-model ກັບຂໍ້ຄວາມທີ່ຈັດກຸ່ມແລ້ວ ຈຶ່ງສົ່ງ "22,239" ມາຈິງ.
        // ກ່ອນແກ້ ຄ່ານີ້ຜ່ານ validate ໄປລົ້ມຢູ່ DecimalString ເປັນ error ດິບ.
        Sanctum::actingAs($this->editor);

        // 200,000 ກີບ ÷ 22,239 = USD 8.99
        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ໄດ້ຮັບເງິນໂອນ',
            'currency_code' => 'LAK',
            'amount' => '200,000',
            'fx_rate_per_usd' => '22,239',
        ])->assertCreated()
            ->assertJsonPath('data.amount.minor', 200000)
            ->assertJsonPath('data.amount_usd.minor', 899);
    });

    it('ຮັບລາຍຮັບເປັນເງິນບາດ ຄືກັນກັບລາຍຈ່າຍ', function () {
        Sanctum::actingAs($this->editor);

        // ฿3,222.00 ÷ 32.22 = USD 100.00 → × 21,760 = 2,176,000 ກີບ
        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ຮັບເງິນບາດ',
            'currency_code' => 'THB',
            'amount' => '3,222.00',
        ])->assertCreated()
            ->assertJsonPath('data.amount.currency', 'THB')
            ->assertJsonPath('data.amount.minor', 322200)
            ->assertJsonPath('data.amount_usd.minor', 10000)
            ->assertJsonPath('data.amount_lak.minor', 2176000);
    });

    it('ປະຕິເສດ rate ທີ່ບໍ່ແມ່ນຕົວເລກເປັນ 422 ບໍ່ແມ່ນ 500', function () {
        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'rate ຜິດຮູບແບບ',
            'currency_code' => 'LAK',
            'amount' => '200,000',
            'fx_rate_per_usd' => '22e3',
        ])->assertStatus(422)->assertJsonValidationErrors('fx_rate_per_usd');
    });

    it('read_only ສ້າງບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->reader);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ລອງສ້າງ',
            'currency_code' => 'USD',
            'amount' => '100.00',
        ])->assertForbidden();
    });

    it('ຄົນນອກໂຄງການເຫັນບໍ່ໄດ້', function () {
        $outsider = memberOf(Project::factory()->create(), 'full_access');

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues")->assertForbidden();
    });
});

describe('ລາຍການລາຍຮັບ', function () {
    it('ຮຽງຕາມເວລາທີ່ບັນທຶກ ບໍ່ແມ່ນວັນທີຮັບເງິນ', function () {
        // ແຖວສຸດທ້າຍມີວັນທີເກົ່າສຸດແຕ່ຫາກໍພິມເຂົ້າ — ຕ້ອງຢູ່ເທິງສຸດ.
        // ຮຽງຕາມວັນທີຮັບເງິນຈະດັນມັນລົງລຸ່ມສຸດ ຈຶ່ງແຍກສອງແບບອອກຈາກກັນໄດ້
        createRevenue($this->project, $this->editor, [
            'title' => 'ໃສ່ກ່ອນໝູ່',
            'received_date' => '2026-08-21',
        ]);
        createRevenue($this->project, $this->editor, [
            'title' => 'ວັນທີໃໝ່ສຸດ',
            'received_date' => '2026-08-30',
        ]);
        createRevenue($this->project, $this->editor, [
            'title' => 'ບັນທຶກລ້າສຸດ',
            'received_date' => '2026-01-05',
        ]);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ບັນທຶກລ້າສຸດ')
            ->assertJsonPath('data.1.title', 'ວັນທີໃໝ່ສຸດ')
            ->assertJsonPath('data.2.title', 'ໃສ່ກ່ອນໝູ່');
    });
});

/**
 * ຕົວກັ່ນຕອງປະຈຳຖັນຢູ່ຫົວຕາຕະລາງ (ColumnFilter.vue) — ໜຶ່ງຖັນ ໜຶ່ງຊຸດພາລາມິເຕີ.
 *
 * ກັ່ນຢູ່ server ບໍ່ແມ່ນຢູ່ browser: ໜ້າຈໍເຫັນເທື່ອລະໜ້າ ການກັ່ນສະເພາະແຖວທີ່
 * ໂຫຼດມາແລ້ວຈະໃຫ້ຄຳຕອບຜິດທັນທີທີ່ມີຫຼາຍກວ່າໜຶ່ງໜ້າ.
 */
describe('ການກັ່ນຕອງຕາມຖັນ', function () {
    it('ກັ່ນຕາມຊ່ວງວັນທີ', function () {
        createRevenue($this->project, $this->editor, ['received_date' => '2026-08-01', 'title' => 'ເກົ່າ']);
        createRevenue($this->project, $this->editor, ['received_date' => '2026-08-15', 'title' => 'ກາງ']);
        createRevenue($this->project, $this->editor, ['received_date' => '2026-08-30', 'title' => 'ໃໝ່']);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?date_from=2026-08-10&date_to=2026-08-20")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ກາງ');
    });

    it('ປະຕິເສດຊ່ວງວັນທີທີ່ກັບຫົວກັບຫາງ', function () {
        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?date_from=2026-08-20&date_to=2026-08-10")
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    });

    it('ກັ່ນຕາມເລກທີ່ບາງສ່ວນ', function () {
        createRevenue($this->project, $this->editor);   // REV-2026-0001
        createRevenue($this->project, $this->editor);   // REV-2026-0002

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?doc_no=0002")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.doc_no', 'REV-2026-0002');
    });

    /**
     * ຖັນ "ລາຍລະອຽດ" ຢູ່ໜ້າຈໍສະແດງຊື່ລາຍການ **ແລະ** ແຫຼ່ງທຶນ — ພິມຊື່ຜູ້ໂອນ
     * ທີ່ເຫັນຢູ່ຕໍ່ຕາແລ້ວຕ້ອງໄດ້ແຖວນັ້ນ ບໍ່ແມ່ນໄດ້ຫວ່າງເປົ່າ
     */
    it('ກັ່ນຖັນລາຍລະອຽດໄດ້ທັງຊື່ລາຍການ ແລະ ຊື່ແຫຼ່ງທຶນ', function () {
        $payer = Payer::factory()->create(['name' => 'Sinexay']);

        createRevenue($this->project, $this->editor, ['title' => 'ເງິນທຶນງວດທີ 2']);
        createRevenue($this->project, $this->editor, ['title' => 'ໂອນເຂົ້າບັນຊີ', 'payer_id' => $payer->id]);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?title=ງວດ")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ເງິນທຶນງວດທີ 2');

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?title=Sinexay")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ໂອນເຂົ້າບັນຊີ');
    });

    /**
     * ຂອບເຂດປຽບທຽບເປັນ USD ບໍ່ແມ່ນສະກຸນທີ່ບັນທຶກ — 21,760,000 ກີບ ຄື USD 1,000
     * ຈຶ່ງຕ້ອງຕິດຢູ່ໃນຊ່ວງ 500–1,500 ຮ່ວມກັບແຖວ USD
     */
    it('ກັ່ນຕາມຂອບເຂດຈຳນວນເງິນ ໂດຍທຽບເປັນ USD ຂ້າມສະກຸນ', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ນ້ອຍ', 'amount' => '100.00']);
        createRevenue($this->project, $this->editor, ['title' => 'ກາງ', 'amount' => '1000.00']);
        createRevenue($this->project, $this->editor, [
            'title' => 'ກີບ',
            'currency_code' => 'LAK',
            'amount' => '21,760,000',
        ]);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?amount_min=500&amount_max=1500")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'ກີບ')
            ->assertJsonPath('data.1.title', 'ກາງ');
    });

    it('ຮັບຂອບເຂດຈຳນວນເງິນທີ່ຈັດກຸ່ມຫຼັກພັນແລ້ວ ("1,500")', function () {
        // ຊ່ອງຢູ່ຫົວຕາຕະລາງຮັບຄ່າແບບທີ່ຄົນພິມ ຄືກັນກັບຊ່ອງໃນຟອມ
        createRevenue($this->project, $this->editor, ['title' => 'ນ້ອຍ', 'amount' => '100.00']);
        createRevenue($this->project, $this->editor, ['title' => 'ໃຫຍ່', 'amount' => '2000.00']);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?amount_max=1,500")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ນ້ອຍ');
    });

    it('ປະຕິເສດຂອບເຂດຈຳນວນເງິນທີ່ບໍ່ແມ່ນຕົວເລກເປັນ 422 ບໍ່ແມ່ນ 500', function () {
        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?amount_min=1e5")
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount_min');
    });

    /**
     * "ບໍ່ມີຫຼັກຖານ" ສົ່ງມາເປັນ has_evidence=0 ເຊິ່ງເປັນຄ່າທີ່ຖືກຕ້ອງ
     * ບໍ່ແມ່ນ "ບໍ່ໄດ້ກັ່ນຕອງ" — ຖ້າໃຊ້ `filled()` ຜິດຈະຄືນທຸກແຖວ
     */
    it('ກັ່ນແຍກແຖວທີ່ມີ ແລະ ບໍ່ມີຫຼັກຖານ', function () {
        $withEvidence = createRevenue($this->project, $this->editor, ['title' => 'ມີໃບໂອນ']);
        $withEvidence->forceFill(['attachments_count' => 2])->save();

        createRevenue($this->project, $this->editor, ['title' => 'ຍັງບໍ່ມີ']);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?has_evidence=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ມີໃບໂອນ');

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?has_evidence=0")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ຍັງບໍ່ມີ');
    });

    it('ກັ່ນຕາມສະຖານະ', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ຍັງເປັນຮ່າງ']);
        $submitted = createRevenue($this->project, $this->editor, ['title' => 'ສົ່ງແລ້ວ']);
        $submitted->forceFill(['status' => 'submitted'])->save();

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?status=submitted")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ສົ່ງແລ້ວ');
    });

    it('ປະຕິເສດສະຖານະທີ່ບໍ່ຮູ້ຈັກ', function () {
        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?status=paid")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    });

    it('ຕົວກັ່ນຕອງຫຼາຍຖັນເຮັດວຽກຮ່ວມກັນແບບ AND', function () {
        createRevenue($this->project, $this->editor, [
            'title' => 'ກົງທຸກເງື່ອນໄຂ',
            'received_date' => '2026-08-15',
            'amount' => '1000.00',
        ]);
        createRevenue($this->project, $this->editor, [
            'title' => 'ວັນທີກົງ ແຕ່ຍອດນ້ອຍ',
            'received_date' => '2026-08-15',
            'amount' => '10.00',
        ]);
        createRevenue($this->project, $this->editor, [
            'title' => 'ຍອດກົງ ແຕ່ນອກຊ່ວງວັນທີ',
            'received_date' => '2026-01-05',
            'amount' => '1000.00',
        ]);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?date_from=2026-08-01&date_to=2026-08-31&amount_min=500")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ກົງທຸກເງື່ອນໄຂ');
    });
});

/**
 * ຮຽງລຳດັບຈາກຫົວຕາຕະລາງ (ColumnSort.vue) — ຖັນດຽວຕໍ່ຄັ້ງ, ວົນສາມຈັງຫວະ
 * ບໍ່ຮຽງ → asc → desc → ບໍ່ຮຽງ. ຮຽງຢູ່ server ບໍ່ແມ່ນຢູ່ browser ດ້ວຍເຫດຜົນ
 * ດຽວກັນກັບການກັ່ນຕອງ: ຮຽງສະເພາະແຖວຂອງໜ້າທີ່ເຫັນຈະໃຫ້ "ຍອດສູງສຸດ" ທີ່ຜິດ.
 */
describe('ການຮຽງລຳດັບຕາມຖັນ', function () {
    it('ບໍ່ໄດ້ສົ່ງ sort ມາ = ຄົງລຳດັບເລີ່ມຕົ້ນ (ລ່າສຸດທີ່ບັນທຶກຂຶ້ນກ່ອນ)', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ໃສ່ກ່ອນ', 'received_date' => '2026-08-30']);
        createRevenue($this->project, $this->editor, ['title' => 'ໃສ່ຫຼັງ', 'received_date' => '2026-01-05']);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ໃສ່ຫຼັງ');
    });

    it('ຮຽງຕາມວັນທີໄດ້ທັງສອງທິດ', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ກາງ', 'received_date' => '2026-05-10']);
        createRevenue($this->project, $this->editor, ['title' => 'ເກົ່າສຸດ', 'received_date' => '2026-01-05']);
        createRevenue($this->project, $this->editor, ['title' => 'ໃໝ່ສຸດ', 'received_date' => '2026-08-30']);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=date&direction=asc")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ເກົ່າສຸດ')
            ->assertJsonPath('data.2.title', 'ໃໝ່ສຸດ');

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=date&direction=desc")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ໃໝ່ສຸດ')
            ->assertJsonPath('data.2.title', 'ເກົ່າສຸດ');
    });

    it('ບໍ່ໄດ້ສົ່ງ direction ມາ = ນ້ອຍໄປໃຫຍ່', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ໃໝ່', 'received_date' => '2026-08-30']);
        createRevenue($this->project, $this->editor, ['title' => 'ເກົ່າ', 'received_date' => '2026-01-05']);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=date")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ເກົ່າ');
    });

    /**
     * ຖັນຈຳນວນເງິນຕ້ອງຮຽງດ້ວຍຍອດ USD ບໍ່ແມ່ນ `amount_minor` — ບໍ່ດັ່ງນັ້ນ
     * 200,000 ກີບ (USD 9) ຈະຖືກຖືວ່າໃຫຍ່ກວ່າ USD 1,000 (100,000 cents)
     */
    it('ຮຽງຈຳນວນເງິນດ້ວຍຍອດ USD ບໍ່ແມ່ນຕົວເລກດິບຂອງແຕ່ລະສະກຸນ', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ພັນໂດລາ', 'amount' => '1000.00']);
        createRevenue($this->project, $this->editor, [
            'title' => 'ສອງແສນກີບ',
            'currency_code' => 'LAK',
            'amount' => '200,000',
        ]);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=amount&direction=desc")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ພັນໂດລາ')
            ->assertJsonPath('data.1.title', 'ສອງແສນກີບ');
    });

    /**
     * ສະຖານະຮຽງຕາມ **ຂັ້ນຕອນ** ບໍ່ແມ່ນຕາມຕົວອັກສອນ. ຖ້າຮຽງຕາມຕົວອັກສອນ
     * ຈະໄດ້ approved, draft, rejected, submitted ເຊິ່ງບໍ່ມີຄວາມໝາຍໃຫ້ໃຜເລີຍ
     */
    it('ຮຽງສະຖານະຕາມລຳດັບຂັ້ນຕອນ ບໍ່ແມ່ນຕາມຕົວອັກສອນ', function () {
        foreach (['approved', 'draft', 'rejected', 'submitted'] as $status) {
            createRevenue($this->project, $this->editor, ['title' => $status])
                ->forceFill(['status' => $status])->save();
        }

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=status&direction=asc")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'draft')
            ->assertJsonPath('data.1.title', 'submitted')
            ->assertJsonPath('data.2.title', 'approved')
            ->assertJsonPath('data.3.title', 'rejected');
    });

    it('ຮຽງຕາມຈຳນວນຫຼັກຖານ', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ບໍ່ມີ']);
        createRevenue($this->project, $this->editor, ['title' => 'ມີສາມ'])
            ->forceFill(['attachments_count' => 3])->save();

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=evidence&direction=desc")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ມີສາມ');
    });

    /**
     * ຄໍລຳຊື່ບໍ່ໄດ້ bind ເປັນພາລາມິເຕີໃນ `orderBy()` — ຮັບຊື່ຈາກ client ໂດຍກົງ
     * ຈະເປັນຮູຮົ່ວ SQL injection ຈຶ່ງຕ້ອງເປັນ whitelist ເທົ່ານັ້ນ
     */
    it('ປະຕິເສດຊື່ຖັນທີ່ບໍ່ຢູ່ໃນລາຍການທີ່ອະນຸຍາດ', function () {
        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=created_by")
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=date&direction=asc;drop")
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');
    });

    it('ຮຽງແລ້ວກັ່ນຕອງພ້ອມກັນໄດ້ ແລະ ລຳດັບຄົງທີ່ຂ້າມໜ້າ', function () {
        // ວັນທີຊ້ຳກັນທັງໝົດ — ຖ້າບໍ່ມີ `id` ເປັນຕົວຕັດສິນສຸດທ້າຍ ແຖວດຽວກັນ
        // ຈະປາກົດສອງໜ້າ ຫຼື ຫາຍໄປລະຫວ່າງໜ້າ
        foreach (['ກ', 'ຂ', 'ຄ', 'ງ'] as $title) {
            createRevenue($this->project, $this->editor, [
                'title' => $title,
                'received_date' => '2026-08-21',
                'amount' => '500.00',
            ]);
        }

        Sanctum::actingAs($this->editor);

        $first = $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=date&direction=asc&amount_min=100&per_page=2&page=1")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data.*.uuid');

        $second = $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?sort=date&direction=asc&amount_min=100&per_page=2&page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data.*.uuid');

        expect(array_intersect($first, $second))->toBe([]);
    });
});

describe('ການແບ່ງໜ້າ', function () {
    it('ຄືນ meta ຄົບທຸກຄໍລຳທີ່ໜ້າຈໍໃຊ້', function () {
        for ($i = 0; $i < 5; $i++) {
            createRevenue($this->project, $this->editor);
        }

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?per_page=2&page=3")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.from', 5)
            ->assertJsonPath('meta.to', 5);
    });

    /**
     * ເມື່ອກ່ອນ `per_page` ຢູ່ນີ້ບໍ່ມີເພດານເລີຍ — `?per_page=100000` ຈະດຶງ
     * ທັງຕາຕະລາງມາເປັນ Resource ພ້ອມກັນ. ບີບລົງ 150 ແທນທີ່ຈະຕອບ 422
     * ເພາະ endpoint ນີ້ບໍ່ໄດ້ validate ຄ່າອື່ນເລີຍ.
     */
    /**
     * ໜ້າຈໍໃຊ້ `?all=1` ເພື່ອໂຫຼດທັງໂຄງການມາໃຫ້ຕາຕະລາງກັ່ນຕອງ/ຮຽງເອງ —
     * ຖ້າ server ຍັງບີບເປັນ 150 ຢູ່ ຕາຕະລາງຈະກັ່ນຈາກຂໍ້ມູນທີ່ບໍ່ຄົບ
     */
    it('all=1 ຄືນທຸກແຖວໃນເທື່ອດຽວ ໂດຍບໍ່ຜ່ານເພດານ per_page', function () {
        for ($i = 0; $i < 5; $i++) {
            createRevenue($this->project, $this->editor);
        }

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?all=1&per_page=2")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 1);
    });

    it('all=1 ຍັງເຄົາລົບຕົວກັ່ນຕອງ', function () {
        createRevenue($this->project, $this->editor, ['title' => 'ເອົາ', 'received_date' => '2026-08-15']);
        createRevenue($this->project, $this->editor, ['title' => 'ບໍ່ເອົາ', 'received_date' => '2026-01-05']);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?all=1&date_from=2026-08-01")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ເອົາ');
    });

    it('ບີບ per_page ທີ່ເກີນ ແລະ ທີ່ບໍ່ຖືກຕ້ອງ ໃຫ້ຢູ່ໃນຂອບເຂດ', function () {
        createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?per_page=100000")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 150);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues?per_page=0")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1);
    });
});

describe('ຂັ້ນຕອນອະນຸມັດ', function () {
    it('ຜູ້ສ້າງອະນຸມັດເອງບໍ່ໄດ້ (segregation of duties)', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/approve")->assertForbidden();
    });

    it('authorize ອະນຸມັດໄດ້ ແລ້ວລາຍການລັອກ', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.is_locked', true);

        // ອະນຸມັດແລ້ວແກ້ບໍ່ໄດ້ ບໍ່ວ່າໃຜ
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/revenues/{$revenue->uuid}", ['title' => 'ແກ້ຫຼັງອະນຸມັດ'])
            ->assertForbidden();
    });

    it('ປະຕິເສດຕ້ອງມີເຫດຜົນ', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/revenues/{$revenue->uuid}/reject", ['reason' => 'ບໍ່ມີໃບໂອນແນບມາ'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    });

    /** ຄູ່ກັບ ExpenseApiTest — ຜ່ານ HTTP ຈຶ່ງເຫັນ Policy, Service ຢ່າງດຽວບໍ່ພໍ */
    it('ລາຍການທີ່ຖືກປະຕິເສດ ສົ່ງຄືນໄດ້', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/reject", ['reason' => 'ຍອດບໍ່ກົງກັບໃບໂອນ'])
            ->assertOk();

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.rejection_reason', null);
    });

    it('ອະນຸມັດລາຍການທີ່ຍັງເປັນ draft ບໍ່ໄດ້ — ຕອບ 403 ບໍ່ແມ່ນ 500', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/approve")->assertForbidden();
    });

    /**
     * `can` ຄືສິ່ງດຽວທີ່ຕັດສິນວ່າປຸ່ມໃດຂຶ້ນຢູ່ໜ້າຈໍ (WorkflowActions.vue) —
     * ຄາດເຄື່ອນຈາກ Policy ເມື່ອໃດ ຜູ້ອະນຸມັດຈະບໍ່ເຫັນປຸ່ມທີ່ຕົນມີສິດກົດ.
     */
    it('ສົ່ງສິດຕໍ່ແຖວມານຳ ໃຫ້ໜ້າຈໍຮູ້ວ່າຈະສະແດງປຸ່ມໃດ', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/submit")
            ->assertOk()
            // ຜູ້ສ້າງ: ສົ່ງແລ້ວ ອະນຸມັດເອງບໍ່ໄດ້
            ->assertJsonPath('data.can.approve', false);

        Sanctum::actingAs($this->approver);
        $this->getJson("/api/v1/projects/{$this->project->uuid}/revenues")
            ->assertOk()
            ->assertJsonPath('data.0.can.approve', true)
            ->assertJsonPath('data.0.can.reject', true);
    });

    it('ອະນຸມັດແລ້ວ ບໍ່ມີໃຜເຮັດຫຍັງໄດ້ອີກ', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/revenues/{$revenue->uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.can', [
                'update' => false,
                'delete' => false,
                'submit' => false,
                'approve' => false,
                'reject' => false,
                // ຍົກເລີກການອະນຸມັດເປັນສິດຂອງຜູ້ດູແລລະບົບເທົ່ານັ້ນ
                'reopen' => false,
            ]);
    });
});

describe('ແກ້ໄຂ ແລະ ລຶບ', function () {
    it('ແກ້ຈຳນວນແລ້ວຍອດແປງຖືກຄິດຄືນ', function () {
        $revenue = createRevenue($this->project, $this->editor, ['amount' => '1000.00']);

        Sanctum::actingAs($this->editor);

        $this->patchJson("/api/v1/revenues/{$revenue->uuid}", ['amount' => '2500.00'])
            ->assertOk()
            ->assertJsonPath('data.amount_usd.minor', 250000);
    });

    it('ເລກ doc_no ບໍ່ຖືກອອກໃໝ່ຕອນແກ້ໄຂ', function () {
        $revenue = createRevenue($this->project, $this->editor);
        $original = $revenue->doc_no;

        Sanctum::actingAs($this->editor);
        $this->patchJson("/api/v1/revenues/{$revenue->uuid}", ['title' => 'ຊື່ໃໝ່'])->assertOk();

        expect($revenue->fresh()->doc_no)->toBe($original);
    });

    it('ລຶບແລ້ວເປັນ soft delete', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->deleteJson("/api/v1/revenues/{$revenue->uuid}")->assertNoContent();

        expect(Revenue::query()->find($revenue->id))->toBeNull()
            ->and(Revenue::withTrashed()->find($revenue->id))->not->toBeNull();
    });

    it('ຜູກແຫຼ່ງລາຍຮັບ (payer) ໄດ້ ແລະ ບໍ່ຜູກກໍໄດ້', function () {
        $payer = Payer::factory()->create(['name' => 'SSS']);

        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ມີແຫຼ່ງ',
            'currency_code' => 'USD',
            'amount' => '500.00',
            'payer_id' => $payer->id,
        ])->assertCreated()->assertJsonPath('data.payer.name', 'SSS');

        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ບໍ່ມີແຫຼ່ງ',
            'currency_code' => 'USD',
            'amount' => '500.00',
        ])->assertCreated()->assertJsonPath('data.payer', null);
    });
});

describe('ທະນາຄານທີ່ເງິນເຂົ້າ', function () {
    it('ບັນທຶກ ແລະ ຄືນທະນາຄານມານຳລາຍການ', function () {
        $bank = Bank::factory()->create(['code' => 'LDB', 'short_name' => 'LDB']);

        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ເງິນທຶນຈາກ SSS',
            'currency_code' => 'USD',
            'amount' => '10,000.00',
            'bank_id' => $bank->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.bank.code', 'LDB');
    });

    /**
     * ຕ່າງຈາກລາຍຈ່າຍໂດຍເຈດຕະນາ: ລາຍຮັບບໍ່ມີ `payment_method` ຈຶ່ງບໍ່ມີ
     * ເງື່ອນໄຂໃດຈະລ້າງທະນາຄານຖິ້ມ — "ເງິນເຂົ້າບັນຊີໃດ" ຖືກສະເໝີ
     */
    it('ບໍ່ຖືກລ້າງຖິ້ມ — ລາຍຮັບບໍ່ມີວິທີຈ່າຍ', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->editor);

        $uuid = $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ເງິນທຶນ',
            'currency_code' => 'USD',
            'amount' => '10,000.00',
            'bank_id' => $bank->id,
        ])->assertCreated()->json('data.uuid');

        $this->patchJson("/api/v1/revenues/{$uuid}", ['title' => 'ແກ້ຫົວຂໍ້ຊື່ໆ'])
            ->assertOk()
            ->assertJsonPath('data.bank.id', $bank->id);
    });

    it('ປະຕິເສດທະນາຄານທີ່ບໍ່ມີຢູ່', function () {
        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/revenues", [
            'received_date' => '2026-08-21',
            'title' => 'ເງິນທຶນ',
            'currency_code' => 'USD',
            'amount' => '10,000.00',
            'bank_id' => 999_999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bank_id');
    });
});

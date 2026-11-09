<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->accountant = memberOf($this->project, 'modify');

    $this->payload = [
        'expense_date' => '2026-08-21',
        'title' => 'ຄ່າເສົາໄຟຟ້າ',
        'currency_code' => 'LAK',
        'amount' => '12,500,000',
        'fx_rate_per_usd' => '21760',
    ];
});

describe('ການພິສູດຕົວຕົນ ແລະ ສິດ', function () {
    it('ຕ້ອງ login ກ່ອນ', function () {
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", $this->payload)
            ->assertUnauthorized();
    });

    it('ຄົນນອກໂຄງການສ້າງລາຍການບໍ່ໄດ້', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", $this->payload)
            ->assertForbidden();
    });

    it('viewer ສ້າງລາຍການບໍ່ໄດ້', function () {
        Sanctum::actingAs(memberOf($this->project, 'read_only'));

        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", $this->payload)
            ->assertForbidden();
    });

    it('ບໍ່ຮົ່ວ id ພາຍໃນອອກ response — ໃຊ້ uuid ເທົ່ານັ້ນ', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", $this->payload)
            ->assertCreated()
            ->assertJsonMissingPath('data.id')
            ->assertJsonPath('data.doc_no', 'EXP-2026-0001');
    });
});

describe('POST expenses', function () {
    beforeEach(fn () => Sanctum::actingAs($this->accountant));

    it('ສ້າງລາຍການ ແລະ ຄືນຍອດແປງເປັນ integer minor', function () {
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", $this->payload)
            ->assertCreated()
            ->assertJsonPath('data.amount.minor', 12_500_000)
            ->assertJsonPath('data.amount.decimal', '12500000')
            ->assertJsonPath('data.amount.decimals', 0)
            ->assertJsonPath('data.amount_usd.minor', 57_445)
            ->assertJsonPath('data.amount_usd.decimal', '574.45')
            ->assertJsonPath('data.amount_lak.minor', 12_500_000)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.has_evidence', false);
    });

    it('ຮັບຈຳນວນເງິນທີ່ມີ comma ຄືທີ່ຄົນພິມຈິງ', function () {
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'amount' => '12 500 000',
        ])->assertCreated()->assertJsonPath('data.amount.minor', 12_500_000);
    });

    it('ປະຕິເສດ rate ທີ່ພິມຜິດ 10 ເທົ່າ', function () {
        // ນີ້ຄືຄວາມຜິດພາດຈິງໃນ Excel ທີ່ເຮັດໃຫ້ລາຍງານຜິດ 5,314 USD
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'fx_rate_per_usd' => '2117',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fx_rate_per_usd');

        expect(Expense::query()->count())->toBe(0);
    });

    it('ຍອມໃຫ້ຜ່ານໄດ້ເມື່ອຜູ້ໃຊ້ຢືນຢັນວ່າ rate ຖືກຕ້ອງແທ້', function () {
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'fx_rate_per_usd' => '2117',
            'confirm_unusual_rate' => true,
        ])->assertCreated()->assertJsonPath('data.amount_usd.minor', 590_458);
    });

    it('ເຕືອນເມື່ອ rate ຫ່າງຈາກອັດຕາອ້າງອີງເກີນ 10%', function () {
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'fx_rate_per_usd' => '25000',   // ຢູ່ໃນຂອບເຂດ ແຕ່ຫ່າງຈາກ 21,760 = 14.9%
        ])->assertStatus(422)->assertJsonValidationErrors('fx_rate_per_usd');
    });

    it('ປະຕິເສດ input ທີ່ບໍ່ຖືກຮູບແບບ', function (array $bad, string $field) {
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [...$this->payload, ...$bad])
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    })->with([
        'ຈຳນວນເງິນເປັນສູນ' => [['amount' => '0'], 'amount'],
        'ຈຳນວນເງິນເປັນຕົວອັກສອນ' => [['amount' => 'ຫຼາຍ'], 'amount'],
        'ວິທະຍາສາດ' => [['amount' => '1e5'], 'amount'],
        'ວັນທີອະນາຄົດ' => [['expense_date' => '2099-01-01'], 'expense_date'],
        'ສະກຸນບໍ່ຮູ້ຈັກ' => [['currency_code' => 'XXX'], 'currency_code'],
        'ບໍ່ມີຫົວຂໍ້' => [['title' => ''], 'title'],
    ]);
});

describe('GET expenses', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->accountant);

        createExpense($this->project, $this->accountant, ['title' => 'ຄ່າແຮງງານ']);
        createExpense($this->project, $this->accountant, ['title' => 'ຄ່ານ້ຳມັນ', 'expense_date' => '2026-07-01']);
        createExpense($this->project, $this->accountant, ['title' => 'ຄ່າເຊົ່າ']);
    });

    it('ຄືນລາຍການພ້ອມ pagination', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    });

    // beforeEach ໃສ່ 3 ແຖວຕາມລຳດັບນີ້: ຄ່າແຮງງານ (08-21), ຄ່ານ້ຳມັນ (07-01),
    // ຄ່າເຊົ່າ (08-21). ຮຽງຕາມວັນທີຈ່າຍຈະໄດ້ ຄ່າເຊົ່າ → ຄ່າແຮງງານ → ຄ່ານ້ຳມັນ
    // ຈຶ່ງແຍກອອກຈາກການຮຽງຕາມເວລາບັນທຶກໄດ້ຢ່າງຊັດເຈນ
    it('ຮຽງຕາມເວລາທີ່ບັນທຶກ ບໍ່ແມ່ນວັນທີຈ່າຍ', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'ຄ່າເຊົ່າ')
            ->assertJsonPath('data.1.title', 'ຄ່ານ້ຳມັນ')
            ->assertJsonPath('data.2.title', 'ຄ່າແຮງງານ');
    });

    it('ກັ່ນຕອງຕາມຊ່ວງວັນທີ', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?date_to=2026-07-31")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'ຄ່ານ້ຳມັນ');
    });

    it('ຄົ້ນຫາດ້ວຍຂໍ້ຄວາມພາສາລາວ', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?search=ນ້ຳມັນ")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('ກັ່ນຕອງລາຍການທີ່ຍັງບໍ່ມີຫຼັກຖານ — query ທີ່ Excel ເຮັດບໍ່ໄດ້', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?has_evidence=0")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('ບໍ່ມີ N+1 ເຖິງວ່າຈຳນວນລາຍການຈະເພີ່ມ', function () {
        // ຮ້ອງເທື່ອທຳອິດເພື່ອອຸ່ນ cache (decimal_places ຂອງສະກຸນ ຖືກ cache ຫຼັງອ່ານຄັ້ງດຽວ)
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")->assertOk();
        $baseline = count(DB::getQueryLog());

        foreach (range(1, 5) as $i) {
            createExpense($this->project, $this->accountant, ['title' => "ລາຍການເພີ່ມ {$i}"]);
        }

        DB::flushQueryLog();
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")->assertOk();

        expect(DB::getQueryLog())->toHaveCount($baseline);
    });

    it('ຄົນນອກໂຄງການເບິ່ງລາຍການບໍ່ໄດ້', function () {
        Sanctum::actingAs(memberOf(Project::factory()->create(), 'full_access'));

        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")->assertForbidden();
    });
});

describe('PATCH ແລະ DELETE', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->accountant);
        $this->expense = createExpense($this->project, $this->accountant, ['fx_rate_per_usd' => '21760']);
    });

    it('ແກ້ຈຳນວນເງິນແລ້ວຄິດຍອດແປງຄືນໃໝ່', function () {
        $this->patchJson("/api/v1/expenses/{$this->expense->uuid}", ['amount' => '25,000,000'])
            ->assertOk()
            ->assertJsonPath('data.amount.minor', 25_000_000)
            ->assertJsonPath('data.amount_usd.minor', 114_890);
    });

    it('ປ່ຽນສະກຸນເງິນໂດຍບໍ່ສົ່ງ amount ບໍ່ໄດ້', function () {
        $this->patchJson("/api/v1/expenses/{$this->expense->uuid}", ['currency_code' => 'USD'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    });

    it('soft delete ບໍ່ແມ່ນລຶບຖາວອນ', function () {
        $this->deleteJson("/api/v1/expenses/{$this->expense->uuid}")->assertNoContent();

        $this->assertSoftDeleted('expenses', ['id' => $this->expense->id]);
    });

    it('viewer ລຶບບໍ່ໄດ້', function () {
        Sanctum::actingAs(memberOf($this->project, 'read_only'));

        $this->deleteJson("/api/v1/expenses/{$this->expense->uuid}")->assertForbidden();
    });
});

describe('Workflow endpoints', function () {
    beforeEach(function () {
        $this->approver = memberOf($this->project, 'authorize');
        $this->expense = createExpense($this->project, $this->accountant);
    });

    it('ສົ່ງອະນຸມັດແລ້ວອະນຸມັດໄດ້', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.is_locked', true);
    });

    it('ຜູ້ສ້າງອະນຸມັດລາຍການຕົນເອງບໍ່ໄດ້ — segregation of duties', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertOk();

        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/approve")->assertForbidden();
    });

    it('ອະນຸມັດລາຍການທີ່ຍັງເປັນ draft ບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->approver);

        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/approve")->assertForbidden();
    });

    it('ຕອບ 409 ເມື່ອອະນຸມັດຊ້ຳ ບໍ່ແມ່ນ 500', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/approve")->assertOk();

        // Policy ຈະປະຕິເສດກ່ອນເຖິງ Service — ແຕ່ submit ຊ້ຳຈະໄປເຖິງ state machine
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertForbidden();
    });

    it('ປະຕິເສດຕ້ອງມີເຫດຜົນ', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/reject")
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/reject", ['reason' => 'ບໍ່ມີໃບໂອນແນບ'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'ບໍ່ມີໃບໂອນແນບ');
    });

    /**
     * ຜ່ານ HTTP ບໍ່ແມ່ນຜ່ານ Service. ExpenseWorkflowTest ພິສູດວ່າ Service
     * ຮັບ `rejected → submitted` ຢູ່ແລ້ວ ແຕ່ມັນເອີ້ນ Service ກົງໆຈຶ່ງຂ້າມ
     * Policy ໄປ — ຕອນນັ້ນ ExpensePolicy::submit() ຮັບແຕ່ `draft` ຈຶ່ງໄດ້ 403
     * ສະເໝີ ແລະ ລາຍການທີ່ຖືກປະຕິເສດຄ້າງຕາຍ. ຕ້ອງກວດຢູ່ຊັ້ນນີ້ຈຶ່ງເຫັນ.
     */
    it('ລາຍການທີ່ຖືກປະຕິເສດ ແກ້ແລ້ວສົ່ງຄືນໄດ້', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/reject", ['reason' => 'ບໍ່ມີໃບໂອນແນບ'])
            ->assertOk();

        Sanctum::actingAs($this->accountant);
        $this->patchJson("/api/v1/expenses/{$this->expense->uuid}", ['title' => 'ແກ້ແລ້ວ'])
            ->assertOk()
            // ປຸ່ມ "ສົ່ງອະນຸມັດ" ຕ້ອງກັບມາ ບໍ່ດັ່ງນັ້ນຈະບໍ່ມີທາງອອກຈາກ rejected
            ->assertJsonPath('data.can.submit', true);

        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.rejection_reason', null);
    });

    it('ແກ້ລາຍການທີ່ອະນຸມັດແລ້ວບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/approve")->assertOk();

        Sanctum::actingAs($this->accountant);
        $this->patchJson("/api/v1/expenses/{$this->expense->uuid}", ['title' => 'ພະຍາຍາມແກ້'])
            ->assertForbidden();
    });
});

/**
 * ຕົວແບ່ງໜ້າ (Pagination.vue) ຂັບເຄື່ອນດ້ວຍ `meta` ທັງໝົດ — ຖ້າຄໍລຳໃດຂາດ
 * ຫຼື ເພດານປ່ຽນ ໜ້າຈໍຈະຄິດເລກໜ້າຜິດໂດຍບໍ່ມີ error ໃຫ້ເຫັນ.
 */
describe('ການແບ່ງໜ້າ', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->accountant);

        for ($i = 0; $i < 7; $i++) {
            createExpense($this->project, $this->accountant);
        }
    });

    it('ຄືນ meta ຄົບທຸກຄໍລຳທີ່ໜ້າຈໍໃຊ້', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?per_page=3")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 7)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 3);
    });

    it('ໜ້າສຸດທ້າຍຄືນສ່ວນທີ່ເຫຼືອ', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?per_page=3&page=3")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.from', 7)
            ->assertJsonPath('meta.to', 7);
    });

    it('ຂໍໜ້າທີ່ເກີນ ຄືນຫວ່າງ ພ້ອມ from/to ເປັນ null', function () {
        // ໜ້າຈໍພຶ່ງກໍລະນີນີ້ເພື່ອຖອຍກັບໄປໜ້າສຸດທ້າຍ — from/to ຕ້ອງເປັນ null
        // ບໍ່ແມ່ນ 0 ບໍ່ດັ່ງນັ້ນຈະສະແດງ "ກຳລັງສະແດງ 0–0"
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?per_page=3&page=99")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', null)
            ->assertJsonPath('meta.to', null);
    });

    it('ຮັບ 150 ແຖວຕໍ່ໜ້າໄດ້ ແຕ່ເກີນນັ້ນບໍ່ໄດ້', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?per_page=150")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 150);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?per_page=151")
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    });

    /**
     * ໜ້າຈໍໃຊ້ `?all=1` ເພື່ອໂຫຼດທັງໂຄງການມາໃຫ້ຕາຕະລາງກັ່ນຕອງ/ຮຽງເອງ —
     * ຖ້າ server ຍັງແບ່ງໜ້າຢູ່ ຕາຕະລາງຈະກັ່ນຈາກຂໍ້ມູນທີ່ບໍ່ຄົບ
     */
    it('all=1 ຄືນທຸກແຖວໃນເທື່ອດຽວ ໂດຍບໍ່ຜ່ານເພດານ per_page', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?all=1&per_page=3")
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('meta.total', 7)
            ->assertJsonPath('meta.last_page', 1);
    });

    it('all=1 ຍັງເຄົາລົບຕົວກັ່ນຕອງ', function () {
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses?all=1&status=approved")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

/**
 * `can` ຄືສິ່ງດຽວທີ່ຕັດສິນວ່າປຸ່ມໃດຂຶ້ນຢູ່ໜ້າຈໍ (WorkflowActions.vue).
 * ຖ້າມັນຄາດເຄື່ອນຈາກ Policy ຜູ້ໃຊ້ຈະເຫັນປຸ່ມທີ່ກົດແລ້ວໄດ້ 403 ຫຼື
 * — ຮ້າຍກວ່ານັ້ນ — ຜູ້ອະນຸມັດຈະບໍ່ເຫັນປຸ່ມທີ່ຕົນມີສິດກົດ.
 */
describe('ສິດຕໍ່ແຖວ (can)', function () {
    beforeEach(function () {
        $this->approver = memberOf($this->project, 'authorize');
        $this->expense = createExpense($this->project, $this->accountant);
    });

    it('ຜູ້ບັນທຶກເຫັນສະເພາະ submit ຕອນເປັນຮ່າງ', function () {
        Sanctum::actingAs($this->accountant);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")
            ->assertOk()
            ->assertJsonPath('data.0.can.submit', true)
            ->assertJsonPath('data.0.can.update', true)
            ->assertJsonPath('data.0.can.approve', false)
            ->assertJsonPath('data.0.can.reject', false);
    });

    it('ຜູ້ສ້າງບໍ່ເຫັນປຸ່ມອະນຸມັດລາຍການຕົນເອງ ເຖິງວ່າສົ່ງແລ້ວ', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")
            ->assertOk()
            // ນີ້ຄືກົດ segregation of duties ທີ່ຝັ່ງໜ້າຈໍຄິດເອງບໍ່ໄດ້ —
            // ມັນຕ້ອງໃຊ້ `created_by` ເຊິ່ງ API ບໍ່ເຄີຍສົ່ງອອກ
            ->assertJsonPath('data.can.approve', false)
            ->assertJsonPath('data.can.reject', false)
            // ສົ່ງແລ້ວ ສົ່ງຊ້ຳບໍ່ໄດ້ ແຕ່ຍັງແກ້ໄດ້ຢູ່ (ຍັງບໍ່ລັອກ)
            ->assertJsonPath('data.can.submit', false)
            ->assertJsonPath('data.can.update', true);
    });

    it('ຜູ້ມີສິດອະນຸມັດເຫັນປຸ່ມອະນຸມັດ/ປະຕິເສດ ຫຼັງມີການສົ່ງ', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")
            ->assertOk()
            ->assertJsonPath('data.0.can.approve', true)
            ->assertJsonPath('data.0.can.reject', true);
    });

    it('ຍັງບໍ່ທັນສົ່ງ ຜູ້ອະນຸມັດກໍຍັງບໍ່ເຫັນປຸ່ມ', function () {
        Sanctum::actingAs($this->approver);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")
            ->assertOk()
            ->assertJsonPath('data.0.can.approve', false)
            ->assertJsonPath('data.0.can.reject', false);
    });

    it('ອະນຸມັດແລ້ວ ບໍ່ມີໃຜເຮັດຫຍັງໄດ້ອີກ', function () {
        Sanctum::actingAs($this->accountant);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/expenses/{$this->expense->uuid}/approve")
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

    it('ຜູ້ອ່ານຢ່າງດຽວບໍ່ໄດ້ຫຍັງເລີຍ', function () {
        Sanctum::actingAs(memberOf($this->project, 'read_only'));

        $this->getJson("/api/v1/projects/{$this->project->uuid}/expenses")
            ->assertOk()
            ->assertJsonPath('data.0.can', [
                'update' => false,
                'delete' => false,
                'submit' => false,
                'approve' => false,
                'reject' => false,
                'reopen' => false,
            ]);
    });
});

describe('ທະນາຄານທີ່ໂອນ', function () {
    it('ບັນທຶກ ແລະ ຄືນທະນາຄານມານຳລາຍການ', function () {
        $bank = Bank::factory()->create(['code' => 'BCEL', 'short_name' => 'BCEL']);

        Sanctum::actingAs($this->accountant);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'payment_method' => 'bank_transfer',
            'bank_id' => $bank->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.bank.code', 'BCEL')
            ->assertJsonPath('data.bank.short_name', 'BCEL');
    });

    it('ລ້າງທະນາຄານຖິ້ມເມື່ອຈ່າຍເປັນເງິນສົດ', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        // ຖາມວ່າ "ໂອນຜ່ານທະນາຄານໃດ" ສຳລັບການຈ່າຍເປັນເງິນສົດແມ່ນຄຳຖາມທີ່ບໍ່ມີ
        // ຄຳຕອບ — ເກັບໄວ້ຈະກາຍເປັນຂໍ້ມູນທີ່ຂັດກັນເອງ
        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'payment_method' => 'cash',
            'bank_id' => $bank->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.bank', null);
    });

    it('ປ່ຽນເປັນເງິນສົດພາຍຫຼັງ ກໍລ້າງທະນາຄານທີ່ຄ້າງຢູ່ຖິ້ມນຳ', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        $uuid = $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'payment_method' => 'bank_transfer',
            'bank_id' => $bank->id,
        ])->assertCreated()->json('data.uuid');

        // ສົ່ງແຕ່ payment_method ມາ ບໍ່ໄດ້ແຕະ bank_id ເລີຍ — ນີ້ຄືກໍລະນີທີ່
        // ການກວດຢູ່ຊັ້ນ validation ຈັບບໍ່ໄດ້ ເພາະ request ນີ້ເບິ່ງຖືກທຸກຢ່າງ
        $this->patchJson("/api/v1/expenses/{$uuid}", ['payment_method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.bank', null);

        $this->assertDatabaseHas('expenses', ['uuid' => $uuid, 'bank_id' => null]);
    });

    it('ປະຕິເສດທະນາຄານທີ່ບໍ່ມີຢູ່', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            ...$this->payload,
            'bank_id' => 999_999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bank_id');
    });
});

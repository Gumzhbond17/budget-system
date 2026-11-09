<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\Reports\ReportPdfLink;
use App\Services\RevenueService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * ພຶດຕິກຳຂອງລິ້ງລາຍງານທີ່ມີລາຍເຊັນ.
 *
 * ## ບັນຫາທີ່ test ຊຸດນີ້ເຝົ້າ
 * ເສັ້ນທາງ PDF ເຄີຍໃຊ້ `auth:sanctum`. Sanctum SPA mode ຖືວ່າ request ເປັນ
 * stateful ກໍຕໍ່ເມື່ອມີ header `Referer`/`Origin` — ແຕ່ການເປີດ URL ໃນແທັບໃໝ່
 * ແລ້ວກົດໂຫຼດຄືນ (ຫຼື ເປີດຈາກ bookmark) **ບໍ່ມີ header ນັ້ນ** ຈຶ່ງຕອບ
 * 401 "Unauthenticated." ທັງທີ່ຜູ້ໃຊ້ຍັງ login ຢູ່.
 *
 * `Sanctum::actingAs()` ຂ້າມການກວດນັ້ນທັງໝົດ ຈຶ່ງບໍ່ມີ test ໃດຈັບໄດ້ເລີຍ.
 * ຊຸດນີ້ຈຶ່ງ **ບໍ່** login ກ່ອນເປີດລິ້ງ — ຄືກັບແທັບໃໝ່ຈິງໆ.
 */
beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create(['code' => 'BS']);
    $this->accountant = memberOf($this->project, 'modify');
    $approver = memberOf($this->project, 'authorize');

    $revenue = createRevenue($this->project, $this->accountant);
    app(RevenueService::class)->submit($revenue);
    app(RevenueService::class)->approve($revenue, $approver->id);

    $expense = createExpense($this->project, $this->accountant, ['fx_rate_per_usd' => '21760']);
    app(ExpenseService::class)->submit($expense);
    app(ExpenseService::class)->approve($expense, $approver->id);

    /** ດຶງລິ້ງທີ່ເຊັນແລ້ວ ຄືກັບທີ່ໜ້າຈໍເຮັດ ແລ້ວລືມ session ຖິ້ມ */
    $this->linkFor = function (string $report): string {
        Sanctum::actingAs($this->accountant);

        $url = $this->getJson("/api/v1/projects/{$this->project->uuid}/reports/{$report}")
            ->assertOk()
            ->json('data.pdf_url');

        expect($url)->toBeString();

        // ຕັດ session ຖິ້ມ — ແທັບໃໝ່ທີ່ຖືກໂຫຼດຄືນກໍບໍ່ມີ session ໃຫ້ Sanctum ຄືກັນ
        app('auth')->forgetGuards();

        return $url;
    };
});

it('ເປີດລິ້ງໄດ້ໂດຍບໍ່ມີ session ເລີຍ — ນີ້ຄືກໍລະນີແທັບໃໝ່/ໂຫຼດຄືນ', function (string $report) {
    $response = $this->get(($this->linkFor)($report))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
})->with(['expenses', 'revenues', 'ledger']);

it('ເປີດໄດ້ເຖິງແມ່ນບໍ່ມີ header Referer — Sanctum ຈະບໍ່ເປີດ session ໃຫ້', function () {
    // ນີ້ຄືສະຖານະການໃນ screenshot: ກົດ F5 ໃນແທັບ PDF ແລ້ວໄດ້ "Unauthenticated."
    $this->withHeaders(['Referer' => ''])
        ->get(($this->linkFor)('ledger'))
        ->assertOk();
});

it('ລິ້ງທີ່ຖືກແກ້ຕົວກັ່ນຕອງໃຊ້ບໍ່ໄດ້', function () {
    $url = ($this->linkFor)('expenses');

    // ຖ້າບໍ່ກວດລາຍເຊັນ ໃຜກໍແກ້ payer_id ແລ້ວເບິ່ງລາຍງານຊຸດອື່ນໄດ້
    $this->get($url.'&payer_id=999')->assertForbidden();
});

it('ລິ້ງທີ່ໝົດອາຍຸໃຊ້ບໍ່ໄດ້', function () {
    $url = ($this->linkFor)('expenses');

    Carbon::setTestNow(now()->addMinutes((int) config('pdf.link_ttl_minutes') + 1));

    $this->get($url)->assertForbidden();

    Carbon::setTestNow();
});

it('ຄົນທີ່ຖືກຖອດອອກຈາກໂຄງການແລ້ວ ໃຊ້ລິ້ງເກົ່າບໍ່ໄດ້', function () {
    $url = ($this->linkFor)('expenses');

    // ລາຍເຊັນພິສູດພຽງວ່າ URL ອອກໂດຍລະບົບ — ບໍ່ໄດ້ພິສູດວ່າ **ຍັງ** ມີສິດຢູ່
    ProjectMember::query()
        ->where('project_id', $this->project->id)
        ->where('user_id', $this->accountant->id)
        ->delete();

    $this->get($url)->assertForbidden();
});

it('ລິ້ງທີ່ຊີ້ໃສ່ຜູ້ໃຊ້ທີ່ບໍ່ມີສິດ ໃຊ້ບໍ່ໄດ້ ເຖິງແມ່ນລາຍເຊັນຖືກຕ້ອງ', function () {
    $outsider = User::factory()->create();

    // ເຊັນດ້ວຍມືໃຫ້ຄົນນອກ — ຈຳລອງກໍລະນີທີ່ລະບົບອອກລິ້ງຜິດຄົນ
    $url = ReportPdfLink::make(
        'reports.export.expenses-pdf',
        $this->project,
        $outsider,
        [],
    );

    $this->get($url)->assertForbidden();
});

it('ລິ້ງບໍ່ມີລາຍເຊັນໃຊ້ບໍ່ໄດ້', function () {
    $this->get("/api/v1/projects/{$this->project->uuid}/reports/export/expenses.pdf")
        ->assertForbidden();
});

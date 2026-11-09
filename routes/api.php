<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\BudgetItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CredentialController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpenseWorkflowController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\PayeeController;
use App\Http\Controllers\Api\PayerController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\RevenueController;
use App\Http\Controllers\Api\RevenueWorkflowController;
use App\Http\Controllers\Api\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // ⚠️ ຢູ່ນອກ `access.active` ໂດຍເຈດຕະນາ — SPA ຕ້ອງດຶງຜູ້ໃຊ້ໄດ້ເຖິງແມ່ນ
    // ອົງກອນໝົດອາຍຸ ຈຶ່ງຈະສະແດງໜ້າ "ໝົດອາຍຸທົດລອງໃຊ້" ພ້ອມຊື່ຜູ້ໃຊ້ໄດ້.
    // ຖ້າເອົາເຂົ້າໄປນຳ ຜູ້ໃຊ້ຈະເຫັນແຕ່ໜ້າຂາວ ບໍ່ຮູ້ວ່າເປັນຫຍັງ.
    Route::get('user', fn (Request $request) => UserResource::make($request->user()->load('company')));

    // ໂປຣໄຟລ໌ຕົນເອງ (ພ້ອມສິດຕໍ່ໂຄງການ) — ຢູ່ນອກ access.active ດ້ວຍເຫດຜົນດຽວກັນ:
    // ຄົນທີ່ໝົດອາຍຸກໍຄວນເຫັນວ່າບັນຊີຕົນໝົດມື້ໃດ
    Route::get('profile', [ProfileController::class, 'show']);

    // ຮູບໂປຣໄຟລ໌ຂອງຕົນເອງ — multipart ຈຶ່ງເປັນ endpoint ຕ່າງຫາກ (ຄືໂລໂກ້ທະນາຄານ)
    Route::post('profile/avatar', [ProfileController::class, 'storeAvatar'])
        ->middleware('throttle:30,1')   // ອັບໂຫຼດແພງກວ່າ request ທຳມະດາຫຼາຍ
        ->name('profile.avatar.store');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])
        ->name('profile.avatar.destroy');
});

// ທຸກເສັ້ນທາງລຸ່ມນີ້ຕ້ອງ login **ແລະ** ອົງກອນຍັງບໍ່ໝົດອາຍຸ/ຖືກລະງັບ.
// `log.search` ບັນທຶກທຸກ `?search=` ລົງ activity_logs (LogSearchActivity)
Route::middleware(['auth:sanctum', 'access.active', 'log.search'])->prefix('v1')->group(function () {

    // ---------- ບັນທຶກການໃຊ້ງານ (audit trail) ----------
    // ອ່ານຢ່າງດຽວ — ບໍ່ມີ route ແກ້ ຫຼື ລຶບ log ໂດຍເຈດຕະນາ (docs/architecture.md ພາກ 9)
    Route::get('activity-logs', [ActivityLogController::class, 'index']);

    // ---------- ຂໍ້ມູນຫຼັກ / ຕັ້ງຄ່າ (ອົງກອນ → ໂຄງການ → ຜູ້ໃຊ້) ----------
    // ຂັ້ນຕອນນີ້ຄືລຳດັບທີ່ໜ້າ Settings ຢູ່ Vue ພາເຮັດຕາມ:
    //   1. ສ້າງອົງກອນ  2. ສ້າງໂຄງການໃຫ້ອົງກອນນັ້ນ  3. ມອບສິດຜູ້ໃຊ້ເຂົ້າໂຄງການ
    Route::apiResource('companies', CompanyController::class)
        ->parameters(['companies' => 'company:uuid']);

    Route::apiResource('projects', ProjectController::class)
        ->parameters(['projects' => 'project:uuid']);

    Route::apiResource('users', UserController::class);

    // ---------- Master data (ໃຊ້ຮ່ວມກັນທຸກໂຄງການ) ----------
    // FK ເປັນ nullOnDelete ຈຶ່ງລຶບປະຫວັດແບບງຽບໆໄດ້. ໝວດ/ຜູ້ຈ່າຍ/ຜູ້ຮັບເງິນ
    // ຈຶ່ງລຶບໄດ້ສະເພາະເມື່ອບໍ່ມີໃຜອ້າງເຖິງ (controller ກວດແລ້ວຕອບ 409) —
    // ຖ້າມີລາຍການຢູ່ນຳໃຫ້ຢຸດໃຊ້ດ້ວຍ is_active = false ແທນ.
    //
    // ສະກຸນເງິນ: ລຶບໄດ້ເມື່ອບໍ່ມີໃຜອ້າງເຖິງ ແຕ່ `decimal_places` ກັບ `code`
    // ແກ້ບໍ່ໄດ້ຫຼັງສ້າງແລ້ວ (ເບິ່ງ CurrencyController) — ບໍ່ມີ show ເພາະ
    // ໜ້າຈັດການໂຫຼດທັງລາຍການຢູ່ແລ້ວ (ມີພຽງບໍ່ເທົ່າໃດແຖວ).
    Route::apiResource('currencies', CurrencyController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // ອັດຕາແລກປ່ຽນບໍ່ມີ destroy ໂດຍເຈດຕະນາ: ລາຍການເກົ່າ snapshot rate ໄວ້ໃນ
    // ແຖວຂອງຕົນແລ້ວ ການລຶບແຖວ rate ຈຶ່ງບໍ່ໄດ້ແກ້ຍອດໃດເລີຍ ມີແຕ່ລຶບຫຼັກຖານວ່າ
    // ຕົວເລກນັ້ນມາຈາກໃສ. ພິມຜິດ → ໃຊ້ update ແກ້ (ມີປະຫວັດ) ບໍ່ແມ່ນລຶບຖິ້ມ.
    Route::get('exchange-rates/lookup', [ExchangeRateController::class, 'lookup'])->name('exchange-rates.lookup');
    Route::post('exchange-rates/check', [ExchangeRateController::class, 'check'])->name('exchange-rates.check');
    Route::apiResource('exchange-rates', ExchangeRateController::class)->only(['index', 'store', 'update']);

    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('payers', PayerController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('payees', PayeeController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // ທະນາຄານ: ລຶບໄດ້ໂດຍບໍ່ຕ້ອງກວດການອ້າງອີງ ເພາະຍັງບໍ່ມີຕາຕະລາງໃດຊີ້ມາຫາ
    // (`payees.bank_name` ຍັງເປັນ string). ໂລໂກ້ເປັນ endpoint ຕ່າງຫາກ ເພາະ
    // ເປັນ multipart ບໍ່ແມ່ນ JSON — ຟອມຈຶ່ງບັນທຶກຂໍ້ມູນກ່ອນ ແລ້ວຄ່ອຍສົ່ງໄຟລ໌.
    Route::post('banks/{bank}/logo', [BankController::class, 'storeLogo'])
        ->middleware('throttle:30,1')   // ອັບໂຫຼດແພງກວ່າ request ທຳມະດາຫຼາຍ
        ->name('banks.logo.store');
    Route::delete('banks/{bank}/logo', [BankController::class, 'destroyLogo'])
        ->name('banks.logo.destroy');
    Route::apiResource('banks', BankController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // ---------- ບັນຊີເຂົ້າໃຊ້ລະບົບ (ສະເພາະຜູ້ດູແລລະບົບ) ----------
    // ບໍ່ແມ່ນຂໍ້ມູນຫຼັກທີ່ໃຊ້ຮ່ວມກັນ: CredentialPolicy ບໍ່ໃຫ້ຜູ້ໃຊ້ທຳມະດາ
    // ແມ່ນແຕ່ອ່ານລາຍຊື່ (ຕ່າງຈາກ MasterDataPolicy ທີ່ເປີດໃຫ້ອ່ານທຸກຄົນ).
    //
    // ຜູກດ້ວຍ uuid ບໍ່ແມ່ນ id — ກັນການໄລ່ເບິ່ງວ່າມີບັນຊີຢູ່ຈັກອັນ.
    //
    // `reveal` ເປັນ POST ບໍ່ແມ່ນ GET ດ້ວຍສອງເຫດຜົນ: ມັນຮັບລະຫັດຜ່ານໃນ body
    // (ຫ້າມຢູ່ໃນ URL ທີ່ຖືກ log) ແລະ ມັນສ້າງແຖວ audit ທຸກເທື່ອ ຈຶ່ງບໍ່ແມ່ນ
    // ການອ່ານທີ່ປອດຜົນຂ້າງຄຽງ. throttle ກັນຄົນທີ່ຍ່າງມາໃຊ້ໜ້າຈໍທີ່ເປີດຄ້າງ
    // ໄວ້ ນັ່ງເດົາລະຫັດຜ່ານຂອງເຈົ້າຂອງເຄື່ອງ.
    Route::post('credentials/{credential:uuid}/reveal', [CredentialController::class, 'reveal'])
        ->middleware('throttle:10,1')
        ->name('credentials.reveal');

    Route::apiResource('credentials', CredentialController::class)
        ->parameters(['credentials' => 'credential:uuid'])
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // ---------- ພາຍໃນໂຄງການ ----------
    // scopeBindings(): record ທີ່ບໍ່ຢູ່ໃນໂຄງການນີ້ຈະເປັນ 404 ບໍ່ແມ່ນ 403 —
    // ບໍ່ຮົ່ວແມ່ນແຕ່ຂໍ້ມູນວ່າ uuid ນັ້ນມີຢູ່ຈິງ (ພາກ 9)
    Route::prefix('projects/{project:uuid}')->scopeBindings()->group(function () {
        // ---------- ມອບສິດຜູ້ໃຊ້ເຂົ້າໂຄງການ ----------
        // {user} ຜູກດ້ວຍ id ບໍ່ແມ່ນ uuid ເພາະຕາຕະລາງ users ບໍ່ມີ uuid —
        // ບໍ່ຮົ່ວຫຍັງເພີ່ມ ເພາະ manageMembers ກວດສິດກ່ອນຢູ່ແລ້ວ.
        Route::get('members', [ProjectMemberController::class, 'index'])->name('members.index');
        Route::post('members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::patch('members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');

        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');

        // ---------- ລາຍຮັບ (ເງິນເຂົ້າ) ----------
        Route::get('revenues', [RevenueController::class, 'index'])->name('revenues.index');
        Route::post('revenues', [RevenueController::class, 'store'])->name('revenues.store');

        // ---------- ບັນຊີແຍກປະເພດ (GL) ----------
        // ລາຍຮັບ + ລາຍຈ່າຍ ໃນລຳດັບເວລາດຽວ ພ້ອມຍອດຄົງເຫຼືອແລ່ນ
        Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');

        Route::get('budget-items', [BudgetItemController::class, 'index'])->name('budget-items.index');
        Route::post('budget-items', [BudgetItemController::class, 'store'])->name('budget-items.store');

        // ---------- ລາຍງານ (aggregate endpoints ສຳລັບ chart) ----------
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('summary', [ReportController::class, 'summary'])->name('summary');
            Route::get('by-category', [ReportController::class, 'byCategory'])->name('by-category');
            Route::get('by-payer', [ReportController::class, 'byPayer'])->name('by-payer');
            // ຄູ່ຝັ່ງລາຍຮັບຂອງອັນເທິງ — ໜ້າຫຼັກໃຊ້ແຕ່ຕົວເລກລວມ ຈຶ່ງບໍ່ຄວນ
            // ຕີ `reports/revenues` ທີ່ດຶງທຸກແຖວ + ເຊັນ URL ຂອງ PDF ນຳ
            Route::get('revenue-by-payer', [ReportController::class, 'revenueByPayer'])
                ->name('revenue-by-payer');
            Route::get('monthly-trend', [ReportController::class, 'monthlyTrend'])->name('monthly-trend');
            Route::get('budget-vs-actual', [ReportController::class, 'budgetVsActual'])->name('budget-vs-actual');
            Route::get('missing-evidence', [ReportController::class, 'missingEvidence'])->name('missing-evidence');

            // ລາຍງານສະບັບເຕັມ: ຍອດລວມ + ສ່ວນສະຫຼຸບ + ທຸກແຖວ ໃນ request ດຽວ.
            // ຄືນທັງໝົດພ້ອມກັນ (ບໍ່ແມ່ນຫຼາຍ endpoint) ເພື່ອໃຫ້ໜ້າຈໍກັບ PDF
            // ມາຈາກຕົວກັ່ນຕອງອັນດຽວກັນສະເໝີ.
            // ຄຳຕອບມີ `pdf_url` (URL ທີ່ເຊັນແລ້ວ) ຕິດມານຳ — ເບິ່ງກຸ່ມ signed ຂ້າງລຸ່ມ
            Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
            Route::get('revenues', [ReportController::class, 'revenues'])->name('revenues');
            Route::get('ledger', [ReportController::class, 'ledger'])->name('ledger');
        });
    });

    Route::apiResource('budget-items', BudgetItemController::class)
        ->only(['show', 'update', 'destroy']);

    // ---------- ຄິວລໍຖ້າອະນຸມັດ (ແຫຼ່ງຂໍ້ມູນຂອງກະດິ່ງແຈ້ງເຕືອນ) ----------
    // ຂ້າມໂຄງການໂດຍເຈດຕະນາ — ຢູ່ນອກ `projects/{project}` ເພາະຜູ້ອະນຸມັດ
    // ຕ້ອງເຫັນທຸກໂຄງການທີ່ຕົນຮັບຜິດຊອບໃນລາຍການດຽວ ບໍ່ຕ້ອງໄລ່ເປີດເທື່ອລະອັນ.
    //
    // ບໍ່ມີ Policy ຢູ່ເສັ້ນທາງນີ້ ເພາະບໍ່ມີ "ວັດຖຸ" ໃຫ້ກວດ — ຕົວ query ຖືກ
    // scope ດ້ວຍ role ຂອງຜູ້ຖາມເອງ ແລ້ວກັ່ນຊ້ຳດ້ວຍ `can('approve')` ຕໍ່ແຖວ
    // (ເບິ່ງ ApprovalController) ຈຶ່ງບໍ່ມີທາງເຫັນແຖວຂອງຄົນອື່ນ.
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');

    // ---------- ລາຍການດ່ຽວ + workflow ----------
    Route::prefix('expenses/{expense:uuid}')->group(function () {
        Route::get('/', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::patch('/', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::post('submit', [ExpenseWorkflowController::class, 'submit'])->name('expenses.submit');
        Route::post('approve', [ExpenseWorkflowController::class, 'approve'])->name('expenses.approve');
        Route::post('reject', [ExpenseWorkflowController::class, 'reject'])->name('expenses.reject');
        Route::post('reopen', [ExpenseWorkflowController::class, 'reopen'])->name('expenses.reopen');

        Route::get('attachments', [AttachmentController::class, 'indexForExpense'])
            ->name('expenses.attachments.index');
        Route::post('attachments', [AttachmentController::class, 'storeForExpense'])
            ->middleware('throttle:30,1')   // ອັບໂຫຼດແພງກວ່າ request ທຳມະດາຫຼາຍ
            ->name('expenses.attachments.store');
    });

    Route::prefix('revenues/{revenue:uuid}')->group(function () {
        Route::get('/', [RevenueController::class, 'show'])->name('revenues.show');
        Route::patch('/', [RevenueController::class, 'update'])->name('revenues.update');
        Route::delete('/', [RevenueController::class, 'destroy'])->name('revenues.destroy');

        Route::post('submit', [RevenueWorkflowController::class, 'submit'])->name('revenues.submit');
        Route::post('approve', [RevenueWorkflowController::class, 'approve'])->name('revenues.approve');
        Route::post('reject', [RevenueWorkflowController::class, 'reject'])->name('revenues.reject');
        Route::post('reopen', [RevenueWorkflowController::class, 'reopen'])->name('revenues.reopen');

        Route::get('attachments', [AttachmentController::class, 'indexForRevenue'])
            ->name('revenues.attachments.index');
        Route::post('attachments', [AttachmentController::class, 'storeForRevenue'])
            ->middleware('throttle:30,1')
            ->name('revenues.attachments.store');
    });

    Route::delete('attachments/{attachment:uuid}', [AttachmentController::class, 'destroy'])
        ->name('attachments.destroy');
});

/*
|--------------------------------------------------------------------------
| ໄຟລ໌ຫຼັກຖານ ແລະ ລາຍງານ PDF — signed routes
|--------------------------------------------------------------------------
|
| ບໍ່ມີ `auth:sanctum` ໂດຍເຈດຕະນາ: ລາຍເຊັນໃນ URL ຄືສິດເຂົ້າເຖິງ.
|
| ຫຼັກຖານ: browser **ບໍ່ສົ່ງ** cookie/header auth ໄປກັບ <img src="..."> ຂ້າມ
| origin ໄດ້ສະເໝີ ແລະ URL ຖືກສ້າງໃຫ້ຫຼັງຈາກ AttachmentPolicy ຜ່ານແລ້ວ.
|
| ລາຍງານ PDF: ການເປີດ URL ໃນແທັບໃໝ່ ບໍ່ມີ header `Referer` ໃນຫຼາຍກໍລະນີ
| (ໂຫຼດຄືນ, bookmark, Referrer-Policy) ເຊິ່ງເຮັດໃຫ້ Sanctum ບໍ່ເປີດ session
| ແລ້ວຕອບ 401 ທັງທີ່ຜູ້ໃຊ້ຍັງ login ຢູ່ — ເບິ່ງ ReportPdfLink.
|
| ອາຍຸສັ້ນທັງສອງ (5 ນາທີ ຮູບເຕັມ, 30 ນາທີ ລາຍງານ) — URL ທີ່ຮົ່ວໃຊ້ບໍ່ໄດ້ດົນ.
|
*/
Route::middleware('signed')->prefix('v1')->group(function () {
    Route::get('attachments/{attachment:uuid}/download', [AttachmentController::class, 'download'])
        ->name('attachments.download');

    Route::get('attachments/{attachment:uuid}/thumbnail', [AttachmentController::class, 'thumbnail'])
        ->name('attachments.thumbnail');

    // ໂລໂກ້ທະນາຄານ — ເຫດຜົນທາງເຕັກນິກອັນດຽວກັບຫຼັກຖານ (<img src> ບໍ່ມີ auth
    // header) ແຕ່ອາຍຸ 24 ຊົ່ວໂມງ ບໍ່ແມ່ນ 5 ນາທີ: ໂລໂກ້ບໍ່ແມ່ນຄວາມລັບ
    // ຈຶ່ງບໍ່ມີເຫດຜົນໃຫ້ຮູບແຕກໃນໜ້າທີ່ເປີດຄ້າງໄວ້ (ເບິ່ງ BankResource)
    Route::get('banks/{bank}/logo', [BankController::class, 'logo'])
        ->name('banks.logo');

    // ຮູບໂປຣໄຟລ໌ — ເຫດຜົນ ແລະ ອາຍຸ 24 ຊົ່ວໂມງ ຄືກັບໂລໂກ້ (ເບິ່ງ UserResource)
    Route::get('users/{user}/avatar', [ProfileController::class, 'avatar'])
        ->name('users.avatar');

    // ຮັກສາເສັ້ນທາງເດີມໄວ້ຄືເກົ່າ — ປ່ຽນແຕ່ວິທີພິສູດສິດ
    Route::prefix('projects/{project:uuid}/reports/export')->name('reports.export.')->group(function () {
        Route::get('expenses.pdf', [ReportExportController::class, 'expenses'])->name('expenses-pdf');
        Route::get('revenues.pdf', [ReportExportController::class, 'revenues'])->name('revenues-pdf');
        Route::get('ledger.pdf', [ReportExportController::class, 'ledger'])->name('ledger-pdf');
    });
});

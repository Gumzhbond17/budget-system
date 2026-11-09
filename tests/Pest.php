<?php

use App\Models\BudgetItem;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Revenue;
use App\Models\User;
use App\Services\BudgetItemService;
use App\Services\ExpenseService;
use App\Services\RevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * ສ້າງຜູ້ໃຊ້ໃໝ່ ແລະ ໃສ່ເປັນສະມາຊິກໂຄງການດ້ວຍ role ທີ່ກຳນົດ.
 *
 * ຜູ້ໃຊ້ຖືກໃສ່ໃນອົງກອນດຽວກັບໂຄງການ — ນັ້ນຄືກົດຂອງລະບົບຈິງ
 * (ProjectMemberController::assertUserBelongsToProjectCompany).
 *
 * @param  'full_access'|'authorize'|'modify'|'read_only'  $role
 */
function memberOf(Project $project, string $role): User
{
    $user = User::factory()->create(['company_id' => $project->company_id]);

    ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    // role ຖືກ memoize ໄວ້ໃນ instance — ລ້າງເພື່ອໃຫ້ອ່ານຄ່າທີ່ຫາກໍໃສ່
    return $user->forgetProjectRoles();
}

/** rate ອ້າງອີງພື້ນຖານ: 1 USD = 21,760 LAK = 32.22 THB */
function seedBaseRates(string $effectiveDate = '2025-01-01'): void
{
    foreach (['LAK' => '21760', 'THB' => '32.22'] as $currency => $rate) {
        ExchangeRate::query()->create([
            'currency_code' => $currency,
            'effective_date' => $effectiveDate,
            'rate_per_usd' => $rate,
            'source' => 'manual',
        ]);
    }
}

/**
 * ສ້າງລາຍຈ່າຍຜ່ານ ExpenseService — ຫ້າມສ້າງດ້ວຍ factory ເພາະຍອດແປງ
 * ຕ້ອງຄິດຢູ່ບ່ອນດຽວ (docs/architecture.md ພາກ 3.3).
 *
 * @param  array<string, mixed>  $overrides
 */
function createExpense(Project $project, User $creator, array $overrides = []): Expense
{
    return app(ExpenseService::class)->create([
        'project_id' => $project->id,
        'expense_date' => '2026-08-21',
        'title' => 'ຄ່າເສົາໄຟຟ້າ',
        'currency_code' => 'LAK',
        'amount' => '12,500,000',
        ...$overrides,
    ], $creator->id);
}

/**
 * ສ້າງລາຍຮັບຜ່ານ RevenueService — ເຫດຜົນດຽວກັນກັບ createExpense():
 * ຍອດແປງຕ້ອງຄິດຢູ່ບ່ອນດຽວ ຫ້າມສ້າງດ້ວຍ factory.
 *
 * @param  array<string, mixed>  $overrides
 */
function createRevenue(Project $project, User $creator, array $overrides = []): Revenue
{
    return app(RevenueService::class)->create([
        'project_id' => $project->id,
        'received_date' => '2026-08-21',
        'title' => 'ເງິນໂອນເຂົ້າໂຄງການ',
        'currency_code' => 'USD',
        'amount' => '1000.00',
        ...$overrides,
    ], $creator->id);
}

/**
 * ສ້າງລາຍການງົບປະມານຜ່ານ BudgetItemService ດ້ວຍເຫດຜົນດຽວກັນ.
 *
 * @param  array<string, mixed>  $overrides
 */
function createBudgetItem(Project $project, array $overrides = []): BudgetItem
{
    return app(BudgetItemService::class)->create([
        'project_id' => $project->id,
        'name' => 'ແຜງໂຊລາ ຂະໜາດ 500 W',
        'unit' => 'pcs',
        'quantity' => '1',
        'currency_code' => 'USD',
        'unit_price' => '1000.00',
        ...$overrides,
    ]);
}

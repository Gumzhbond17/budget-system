<?php

declare(strict_types=1);

use App\Domain\Money\Money;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->user = User::factory()->create();
    $this->project = Project::factory()->create();

    // rate ອ້າງອີງໃນລະບົບ — ຜູ້ໃຊ້ override ໄດ້ຕໍ່ລາຍການ.
    // ຕັ້ງແຕ່ 2025 ເພື່ອໃຫ້ລາຍການປີ 2025 ຫາ rate ພົບເຊັ່ນກັນ (rateFor ຖອຍຫຼັງເທົ່ານັ້ນ)
    ExchangeRate::query()->create([
        'currency_code' => 'LAK',
        'effective_date' => '2025-01-01',
        'rate_per_usd' => '21760',
        'source' => 'manual',
    ]);
    ExchangeRate::query()->create([
        'currency_code' => 'THB',
        'effective_date' => '2025-01-01',
        'rate_per_usd' => '32.22',
        'source' => 'manual',
    ]);

    $this->service = app(ExpenseService::class);

    $this->makeExpense = fn (array $overrides = []): Expense => $this->service->create([
        'project_id' => $this->project->id,
        'expense_date' => '2026-08-21',
        'title' => 'ຄ່າເສົາໄຟຟ້າ',
        'currency_code' => 'LAK',
        'amount' => '12,500,000',
        ...$overrides,
    ], $this->user->id);
});

describe('ການແປງສະກຸນ', function () {
    /*
     * ຄຳຂໍເດີມຂຽນວ່າ "12,500,000 LAK @ rate 21760 = 574.05 USD" ແຕ່ເລກສອງອັນນັ້ນມາຄົນລະບ່ອນ:
     *   - 21,760 ຄືຕົວຢ່າງ rate ໃນ docs/architecture.md ພາກ 2.1.ຂ
     *   - 574.05 ຄືຜົນລັບໃນ comment ຂອງ ExpenseService::toUsdMinor() ພາກ 3.3
     * ຄິດຈິງແລ້ວ 12,500,000 / 21,760 = 574.4485… → $574.45
     * ສ່ວນ $574.05 ມາຈາກ rate 21,775 (12,500,000 / 21,775 = 574.0528…)
     * ຈຶ່ງພິສູດທັງສອງກໍລະນີ.
     */

    it('12,500,000 LAK @ rate 21,760 = 574.45 USD', function () {
        $expense = ($this->makeExpense)(['fx_rate_per_usd' => '21760']);

        expect($expense->amount_usd_minor)->toBe(57_445)              // cents
            ->and(Money::fromDecimal('574.45', 'USD', 2)->minor)->toBe(57_445)
            ->and((new Money($expense->amount_usd_minor, 'USD', 2))->toDecimal())->toBe('574.45');
    });

    it('12,500,000 LAK @ rate 21,775 = 574.05 USD (ຕົວຢ່າງໃນ docs ພາກ 3.3)', function () {
        $expense = ($this->makeExpense)(['fx_rate_per_usd' => '21775']);

        expect($expense->amount_usd_minor)->toBe(57_405)
            ->and((new Money($expense->amount_usd_minor, 'USD', 2))->toDecimal())->toBe('574.05');
    });

    it('ລາຍການກີບເກັບຍອດກີບຄືເດີມ ບໍ່ຜ່ານການແປງໄປກັບ', function () {
        $expense = ($this->makeExpense)(['fx_rate_per_usd' => '21760']);

        // ຖ້າແປງ LAK → USD → LAK ຈະໄດ້ 12,499,999 ຫຼື 12,500,001 — ນັ້ນຄື rounding drift ທີ່ຫ້າມເກີດ
        expect($expense->amount_lak_minor)->toBe(12_500_000);
    });

    it('ລາຍການ USD ບໍ່ຜ່ານການແປງ ແລະ ຄິດກີບຜ່ານ rate ຂອງວັນນັ້ນ', function () {
        $expense = ($this->makeExpense)([
            'currency_code' => 'USD',
            'amount' => '12.34',
        ]);

        expect($expense->amount_usd_minor)->toBe(1_234)
            ->and($expense->amount_lak_minor)->toBe(268_518);   // 12.34 × 21,760 = 268,518.4
    });

    it('ລາຍການບາດແປງຜ່ານ USD ເປັນສະກຸນກາງ (triangulation)', function () {
        // ພາບໜ້າຈໍການຈອງໂຮງແຮມ THB 2,265 ໃນ sheet ຫຼັກຖານ — docs ພາກ 1.4
        $expense = ($this->makeExpense)([
            'currency_code' => 'THB',
            'amount' => '2265.00',
            'fx_rate_per_usd' => '32.22',
        ]);

        expect($expense->amount_usd_minor)->toBe(7_030)          // 2,265 / 32.22 = 70.2979…
            ->and($expense->amount_lak_minor)->toBe(1_529_683);  // 70.2979… × 21,760
    });

    it('ໃຊ້ rate ຈາກຕາຕະລາງເມື່ອຜູ້ໃຊ້ບໍ່ໄດ້ປ້ອນມາ', function () {
        $expense = ($this->makeExpense)();   // ບໍ່ສົ່ງ fx_rate_per_usd

        expect((float) $expense->fx_rate_per_usd)->toBe(21_760.0)
            ->and($expense->amount_usd_minor)->toBe(57_445);
    });
});

describe('ຂໍ້ຜິດພາດ rate ທີ່ພົບໃນ Excel ເດີມ', function () {
    it('ສະແດງໃຫ້ເຫັນວ່າ rate 2117 ແທນ 21170 ເຮັດໃຫ້ຍອດຜິດ 10 ເທົ່າ', function () {
        // ແຖວ 155 ຂອງ sheet `Payment new` — docs/architecture.md ພາກ 1.4 ຂໍ້ 2
        $typo = ($this->makeExpense)(['fx_rate_per_usd' => '2117']);
        $correct = ($this->makeExpense)(['fx_rate_per_usd' => '21170']);

        expect($typo->amount_usd_minor)->toBe(590_458)        // $5,904.58 — ຄືກັບທີ່ບັນທຶກຜິດໃນ Excel
            ->and($correct->amount_usd_minor)->toBe(59_046)   // $590.46 — ຄ່າທີ່ຄວນເປັນ
            ->and($typo->amount_usd_minor - $correct->amount_usd_minor)->toBe(531_412);   // +$5,314.12
    });
});

describe('ຄ່າຕັ້ງຕົ້ນ ແລະ ຄວາມຄົງທົນຂອງຂໍ້ມູນ', function () {
    it('ເກັບຈຳນວນເງິນເປັນ integer minor units ໃນ DB', function () {
        $expense = ($this->makeExpense)(['fx_rate_per_usd' => '21760']);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'currency_code' => 'LAK',
            'amount_minor' => 12_500_000,
            'amount_usd_minor' => 57_445,
            'amount_lak_minor' => 12_500_000,
        ]);
    });

    it('ຄືນ amount_minor ເປັນ Money ພ້ອມ decimals ຂອງສະກຸນ', function () {
        $expense = ($this->makeExpense)(['fx_rate_per_usd' => '21760'])->fresh();

        expect($expense->amount_minor)->toBeInstanceOf(Money::class)
            ->and($expense->amount_minor->currency)->toBe('LAK')
            ->and($expense->amount_minor->decimals)->toBe(0)
            ->and($expense->amount_minor->toDecimal())->toBe('12500000');
    });

    it('snapshot rate ໄວ້ໃນແຖວ — ແກ້ຕາຕະລາງ exchange_rates ຍ້ອນຫຼັງບໍ່ກະທົບ', function () {
        $expense = ($this->makeExpense)(['fx_rate_per_usd' => '21760']);

        ExchangeRate::query()->where('currency_code', 'LAK')->update(['rate_per_usd' => '99999']);

        expect((float) $expense->fresh()->fx_rate_per_usd)->toBe(21_760.0)
            ->and($expense->fresh()->amount_usd_minor)->toBe(57_445);
    });

    it('ຕັ້ງ uuid, ຜູ້ສ້າງ ແລະ ສະຖານະ draft', function () {
        $expense = ($this->makeExpense)();

        expect($expense->uuid)->toBeString()->toHaveLength(36)
            ->and($expense->created_by)->toBe($this->user->id)
            ->and($expense->status)->toBe('draft')
            ->and($expense->approved_at)->toBeNull();
    });

    it('ອອກເລກໃບສຳຄັນຈ່າຍຕາມລຳດັບຕໍ່ໂຄງການ ຕໍ່ປີ', function () {
        $first = ($this->makeExpense)();
        $second = ($this->makeExpense)();
        $otherProject = ($this->makeExpense)(['project_id' => Project::factory()->create()->id]);
        $lastYear = ($this->makeExpense)(['expense_date' => '2025-12-31']);

        expect($first->doc_no)->toBe('EXP-2026-0001')
            ->and($second->doc_no)->toBe('EXP-2026-0002')
            ->and($otherProject->doc_no)->toBe('EXP-2026-0001')   // ນັບແຍກຕໍ່ໂຄງການ
            ->and($lastYear->doc_no)->toBe('EXP-2025-0001');      // ນັບແຍກຕໍ່ປີ
    });

    it('ບໍ່ນຳເລກທີ່ຖືກ soft-delete ແລ້ວກັບມາໃຊ້ຄືນ', function () {
        ($this->makeExpense)()->delete();

        expect(($this->makeExpense)()->doc_no)->toBe('EXP-2026-0002');
    });

    it('ປະຕິເສດ rate ທີ່ບໍ່ແມ່ນຈຳນວນທົດສະນິຍົມທຳມະດາ', function (string $rate) {
        expect(fn () => ($this->makeExpense)(['fx_rate_per_usd' => $rate]))
            ->toThrow(InvalidArgumentException::class, 'fx_rate_per_usd must be a plain decimal number')
            ->and(Expense::query()->count())->toBe(0);
    })->with(['21,760', '2.176e4', 'ບໍ່ຮູ້']);

    it('ບໍ່ບັນທຶກຫຍັງເລີຍເມື່ອບໍ່ພົບ rate ອ້າງອີງ (rollback)', function () {
        ExchangeRate::query()->delete();

        expect(fn () => ($this->makeExpense)())->toThrow(RuntimeException::class)
            ->and(Expense::query()->count())->toBe(0);
    });
});

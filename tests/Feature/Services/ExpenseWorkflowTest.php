<?php

declare(strict_types=1);

use App\Domain\Expense\InvalidStatusTransition;
use App\Models\Project;
use App\Services\ExpenseService;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->accountant = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');
    $this->service = app(ExpenseService::class);
});

describe('ການປ່ຽນສະຖານະ', function () {
    it('ລາຍການໃໝ່ເລີ່ມທີ່ draft', function () {
        expect(createExpense($this->project, $this->accountant)->status)->toBe('draft');
    });

    it('draft → submitted', function () {
        $expense = createExpense($this->project, $this->accountant);

        expect($this->service->submit($expense)->status)->toBe('submitted');
    });

    it('submitted → approved ພ້ອມບັນທຶກຜູ້ອະນຸມັດ ແລະ ເວລາ', function () {
        $expense = $this->service->submit(createExpense($this->project, $this->accountant));

        $approved = $this->service->approve($expense, $this->approver->id);

        expect($approved->status)->toBe('approved')
            ->and($approved->approved_by)->toBe($this->approver->id)
            ->and($approved->approved_at)->not->toBeNull()
            ->and($approved->isLocked())->toBeTrue();
    });

    it('submitted → rejected ພ້ອມເຫດຜົນ', function () {
        $expense = $this->service->submit(createExpense($this->project, $this->accountant));

        $rejected = $this->service->reject($expense, $this->approver->id, 'ບໍ່ມີໃບໂອນແນບ');

        expect($rejected->status)->toBe('rejected')
            ->and($rejected->rejection_reason)->toBe('ບໍ່ມີໃບໂອນແນບ')
            ->and($rejected->approved_at)->toBeNull();
    });

    it('rejected → submitted ໄດ້ ແລະ ລ້າງເຫດຜົນເກົ່າ', function () {
        $expense = $this->service->submit(createExpense($this->project, $this->accountant));
        $this->service->reject($expense, $this->approver->id, 'ບໍ່ມີໃບໂອນແນບ');

        $resubmitted = $this->service->submit($expense);

        expect($resubmitted->status)->toBe('submitted')
            ->and($resubmitted->rejection_reason)->toBeNull();
    });

    it('ຂ້າມຂັ້ນບໍ່ໄດ້', function (string $method) {
        $draft = createExpense($this->project, $this->accountant);

        expect(fn () => $this->service->{$method}($draft, $this->approver->id, 'ເຫດຜົນ'))
            ->toThrow(InvalidStatusTransition::class);
    })->with(['approve', 'reject']);

    it('submit ຊ້ຳບໍ່ໄດ້', function () {
        $expense = $this->service->submit(createExpense($this->project, $this->accountant));

        expect(fn () => $this->service->submit($expense))
            ->toThrow(InvalidStatusTransition::class, "ປ່ຽນສະຖານະຈາກ 'submitted' ໄປ 'submitted' ບໍ່ໄດ້");
    });

    it('ອະນຸມັດຊ້ຳບໍ່ໄດ້', function () {
        $expense = $this->service->submit(createExpense($this->project, $this->accountant));
        $this->service->approve($expense, $this->approver->id);

        expect(fn () => $this->service->approve($expense, $this->approver->id))
            ->toThrow(InvalidStatusTransition::class);
    });
});

describe('ການແກ້ໄຂ', function () {
    it('ຄິດຍອດແປງຄືນໃໝ່ເມື່ອປ່ຽນຈຳນວນເງິນ', function () {
        $expense = createExpense($this->project, $this->accountant, ['fx_rate_per_usd' => '21760']);

        $this->service->update($expense, ['amount' => '25,000,000']);

        expect($expense->amount_minor->minor)->toBe(25_000_000)
            ->and($expense->amount_usd_minor)->toBe(114_890)      // 25,000,000 / 21,760 = 1,148.897…
            ->and($expense->amount_lak_minor)->toBe(25_000_000);
    });

    it('ຮັກສາ snapshot ຂອງ rate ເມື່ອແກ້ແຕ່ຫົວຂໍ້', function () {
        // rate ໃນຕາຕະລາງແມ່ນ 21,760 ແຕ່ລາຍການນີ້ບັນທຶກດ້ວຍ 21,500
        $expense = createExpense($this->project, $this->accountant, ['fx_rate_per_usd' => '21500']);

        $this->service->update($expense, ['title' => 'ຄ່າເສົາໄຟຟ້າ (ແກ້ໄຂ)']);

        expect((float) $expense->fresh()->fx_rate_per_usd)->toBe(21_500.0)
            ->and($expense->fresh()->amount_usd_minor)->toBe(58_140);   // 12,500,000 / 21,500 = 581.395…
    });

    it('ບໍ່ອອກເລກໃບສຳຄັນຈ່າຍໃໝ່', function () {
        $expense = createExpense($this->project, $this->accountant);
        $docNo = $expense->doc_no;

        $this->service->update($expense, ['title' => 'ແກ້ໄຂ']);

        expect($expense->fresh()->doc_no)->toBe($docNo);
    });

    it('ປ່ຽນສະກຸນເງິນໄດ້ເມື່ອສົ່ງ amount ມານຳ', function () {
        $expense = createExpense($this->project, $this->accountant);

        $this->service->update($expense, [
            'currency_code' => 'THB',
            'amount' => '2265.00',
            'fx_rate_per_usd' => '32.22',
        ]);

        expect($expense->currency_code)->toBe('THB')
            ->and($expense->amount_minor->minor)->toBe(226_500)
            ->and($expense->amount_minor->decimals)->toBe(2)
            ->and($expense->amount_usd_minor)->toBe(7_030)
            ->and($expense->amount_lak_minor)->toBe(1_529_683);
    });

    it('ປະຕິເສດການປ່ຽນສະກຸນເງິນໂດຍບໍ່ສົ່ງ amount', function () {
        $expense = createExpense($this->project, $this->accountant);

        expect(fn () => $this->service->update($expense, ['currency_code' => 'USD']))
            ->toThrow(InvalidArgumentException::class, 'ປ່ຽນສະກຸນເງິນຕ້ອງສົ່ງ amount ມານຳ');
    });

    it('ແກ້ລາຍການທີ່ອະນຸມັດແລ້ວບໍ່ໄດ້', function () {
        $expense = $this->service->submit(createExpense($this->project, $this->accountant));
        $this->service->approve($expense, $this->approver->id);

        expect(fn () => $this->service->update($expense, ['title' => 'ພະຍາຍາມແກ້']))
            ->toThrow(InvalidStatusTransition::class, 'ຕ້ອງສ້າງລາຍການປັບປຸງ (adjustment) ໃໝ່');
    });
});

describe('fx_rate_lak_per_usd', function () {
    it('ໃຊ້ rate ດຽວກັນກັບ fx_rate_per_usd ເມື່ອສະກຸນເປັນກີບ', function () {
        // ຕາຕະລາງມີ 21,760 ແຕ່ຜູ້ໃຊ້ແລກຈິງທີ່ 21,500 —
        // ຖ້າເກັບ 21,760 ໄວ້ໃນຄໍລຳນີ້ ມັນຈະບໍ່ກົງກັບ amount_usd_minor ຂອງແຖວຕົນເອງ
        $expense = createExpense($this->project, $this->accountant, ['fx_rate_per_usd' => '21500']);

        expect((float) $expense->fx_rate_lak_per_usd)->toBe(21_500.0);
    });

    it('ໃຊ້ rate ຈາກຕາຕະລາງເມື່ອສະກຸນບໍ່ແມ່ນກີບ', function () {
        $expense = createExpense($this->project, $this->accountant, [
            'currency_code' => 'THB',
            'amount' => '2265.00',
            'fx_rate_per_usd' => '32.22',
        ]);

        expect((float) $expense->fx_rate_lak_per_usd)->toBe(21_760.0);
    });
});

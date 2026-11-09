<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseService;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->accountant = memberOf($this->project, 'modify');
    $this->expense = createExpense($this->project, $this->accountant);
});

describe('ກັນ IDOR — ຕ້ອງເປັນສະມາຊິກກ່ອນ', function () {
    it('ຄົນນອກໂຄງການເບິ່ງລາຍການບໍ່ໄດ້', function () {
        expect(User::factory()->create()->can('view', $this->expense))->toBeFalse();
    });

    it('ສະມາຊິກໂຄງການອື່ນເບິ່ງລາຍການຂ້າມໂຄງການບໍ່ໄດ້', function () {
        $outsider = memberOf(Project::factory()->create(), 'full_access');

        expect($outsider->can('view', $this->expense))->toBeFalse();
    });

    it('ສະມາຊິກທຸກ role ເບິ່ງໄດ້', function (string $role) {
        expect(memberOf($this->project, $role)->can('view', $this->expense))->toBeTrue();
    })->with(['full_access', 'authorize', 'modify', 'read_only']);
});

describe('ລາຍການທີ່ອະນຸມັດແລ້ວ lock ຖາວອນ', function () {
    beforeEach(function () {
        $approver = memberOf($this->project, 'authorize');
        $service = app(ExpenseService::class);

        $service->submit($this->expense);
        $service->approve($this->expense, $approver->id);
    });

    it('ບໍ່ມີໃຜແກ້ໄດ້ ເຖິງວ່າຈະເປັນ owner', function (string $role) {
        expect(memberOf($this->project, $role)->can('update', $this->expense))->toBeFalse();
    })->with(['full_access', 'modify']);

    it('ຜູ້ສ້າງເອງກໍແກ້ບໍ່ໄດ້', function () {
        expect($this->accountant->can('update', $this->expense))->toBeFalse();
    });

    it('ລຶບ ຫຼື ແນບຫຼັກຖານເພີ່ມກໍບໍ່ໄດ້', function () {
        $owner = memberOf($this->project, 'full_access');

        expect($owner->can('delete', $this->expense))->toBeFalse()
            ->and($owner->can('attach', $this->expense))->toBeFalse();
    });
});

describe('ສິດແກ້ໄຂ draft', function () {
    it('ຜູ້ສ້າງແກ້ລາຍການຕົນເອງໄດ້', function () {
        expect($this->accountant->can('update', $this->expense))->toBeTrue();
    });

    it('owner ແກ້ລາຍການຄົນອື່ນໄດ້', function () {
        expect(memberOf($this->project, 'full_access')->can('update', $this->expense))->toBeTrue();
    });

    it('viewer ແກ້ລາຍການຄົນອື່ນບໍ່ໄດ້', function () {
        expect(memberOf($this->project, 'read_only')->can('update', $this->expense))->toBeFalse();
    });

    it('approver ບໍ່ແມ່ນຜູ້ບັນທຶກ — ແກ້ລາຍການຄົນອື່ນບໍ່ໄດ້', function () {
        expect(memberOf($this->project, 'authorize')->can('update', $this->expense))->toBeFalse();
    });

    it('ສ້າງລາຍການໄດ້ສະເພາະ owner ແລະ accountant', function (string $role, bool $allowed) {
        // ສົ່ງເປັນ [class, ...args] ເພື່ອໃຫ້ Gate ເລືອກ ExpensePolicy ບໍ່ແມ່ນ ProjectPolicy
        expect(memberOf($this->project, $role)->can('create', [Expense::class, $this->project]))
            ->toBe($allowed);
    })->with([
        ['full_access', true],
        ['modify', true],
        ['authorize', false],
        ['read_only', false],
    ]);
});

describe('Segregation of duties ໃນການອະນຸມັດ', function () {
    beforeEach(function () {
        app(ExpenseService::class)->submit($this->expense);
    });

    it('ຜູ້ສ້າງອະນຸມັດລາຍການຕົນເອງບໍ່ໄດ້ ເຖິງວ່າຈະເປັນ owner', function () {
        $owner = memberOf($this->project, 'full_access');
        $ownExpense = createExpense($this->project, $owner, ['title' => 'ລາຍການຂອງ owner ເອງ']);

        app(ExpenseService::class)->submit($ownExpense);

        expect($owner->can('approve', $ownExpense))->toBeFalse();
    });

    it('approver ອະນຸມັດລາຍການຄົນອື່ນໄດ້', function () {
        expect(memberOf($this->project, 'authorize')->can('approve', $this->expense))->toBeTrue();
    });

    it('accountant ອະນຸມັດບໍ່ໄດ້ — ບໍ່ແມ່ນ role ອະນຸມັດ', function () {
        expect(memberOf($this->project, 'modify')->can('approve', $this->expense))->toBeFalse();
    });

    it('ອະນຸມັດລາຍການທີ່ຍັງເປັນ draft ບໍ່ໄດ້', function () {
        $draft = createExpense($this->project, $this->accountant, ['title' => 'ຍັງເປັນ draft']);

        expect(memberOf($this->project, 'authorize')->can('approve', $draft))->toBeFalse();
    });

    it('ປະຕິເສດໃຊ້ກົດດຽວກັນກັບການອະນຸມັດ', function () {
        expect(memberOf($this->project, 'authorize')->can('reject', $this->expense))->toBeTrue()
            ->and($this->accountant->can('reject', $this->expense))->toBeFalse();
    });
});

<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\BudgetItemService;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->service = app(BudgetItemService::class);
});

describe('ການຄິດຍອດ', function () {
    it('ຄິດ amount = quantity × unit_price ໃຫ້ເອງ', function () {
        $item = createBudgetItem($this->project, [
            'quantity' => '2.5',
            'unit_price' => '1000.00',
        ]);

        expect($item->unit_price_minor->minor)->toBe(100_000)     // $1,000.00
            ->and($item->amount_minor->minor)->toBe(250_000)      // $2,500.00
            ->and($item->amount_minor->toDecimal())->toBe('2500.00');
    });

    it('USD ບໍ່ຕ້ອງມີ rate ໃນຕາຕະລາງເລີຍ', function () {
        $item = createBudgetItem($this->project, ['unit_price' => '1493145.00']);

        // ຍອດ BOQ ຈິງໃນ sheet ຕົ້ນທຶນ = USD 1,493,145
        expect($item->amount_usd_minor)->toBe(149_314_500)
            ->and((float) $item->fx_rate_per_usd)->toBe(1.0);
    });

    it('ແປງງົບທີ່ຕັ້ງເປັນກີບໃຫ້ເປັນ USD', function () {
        $item = createBudgetItem($this->project, [
            'currency_code' => 'LAK',
            'unit_price' => '12500000',
            'fx_rate_per_usd' => '21760',
        ]);

        expect($item->amount_minor->minor)->toBe(12_500_000)
            ->and($item->amount_minor->decimals)->toBe(0)
            ->and($item->amount_usd_minor)->toBe(57_445);   // ຄືກັນກັບ ExpenseService
    });

    it('ຄິດຈຳນວນທີ່ມີເສດໄດ້ໂດຍບໍ່ເສຍຄວາມແມ່ນຍຳ', function () {
        $item = createBudgetItem($this->project, [
            'quantity' => '2.75',
            'unit_price' => '8.29',   // (int) (8.29 * 100) = 828 ໃນ float
        ]);

        // 2.75 × 829 cents = 2279.75 → 2280 cents = $22.80
        expect($item->unit_price_minor->minor)->toBe(829)
            ->and($item->amount_minor->minor)->toBe(2_280);
    });

    it('ປະຕິເສດຈຳນວນທີ່ bcmath ໃຊ້ບໍ່ໄດ້', function () {
        expect(fn () => createBudgetItem($this->project, ['unit_price' => '1e5']))
            ->toThrow(InvalidArgumentException::class, 'Money amount must be a plain decimal number');
    });
});

describe('ການແກ້ໄຂ', function () {
    it('ຄິດຍອດ USD ຄືນໃໝ່ເມື່ອປ່ຽນຈຳນວນ', function () {
        $item = createBudgetItem($this->project, ['quantity' => '1', 'unit_price' => '1000.00']);

        $this->service->update($item, ['quantity' => '3']);

        expect($item->amount_minor->minor)->toBe(300_000)
            ->and($item->amount_usd_minor)->toBe(300_000);
    });

    it('ຮັກສາ snapshot ຂອງ rate ເມື່ອແກ້ແຕ່ຊື່', function () {
        $item = createBudgetItem($this->project, [
            'currency_code' => 'LAK',
            'unit_price' => '12500000',
            'fx_rate_per_usd' => '21500',
        ]);

        $this->service->update($item, ['name' => 'ແຜງໂຊລາ (ແກ້ໄຂ)']);

        expect((float) $item->fresh()->fx_rate_per_usd)->toBe(21_500.0)
            ->and($item->fresh()->amount_usd_minor)->toBe(58_140);
    });
});

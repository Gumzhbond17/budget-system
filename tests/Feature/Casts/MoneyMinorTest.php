<?php

declare(strict_types=1);

use App\Casts\MoneyMinor;
use App\Domain\Money\Money;
use Database\Seeders\CurrencySeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast ນີ້ຢູ່ໃນ Feature ບໍ່ແມ່ນ Unit ເພາະ Currency::decimalsFor() ຕ້ອງການ DB + cache —
 * decimal_places ມາຈາກຕາຕະລາງ currencies ບໍ່ແມ່ນ hardcode (docs/architecture.md ພາກ 2.1.ກ).
 */
beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->model = new class extends Model {};
});

describe('get', function () {
    it('ແປງ BIGINT minor ເປັນ Money ພ້ອມ decimals ຈາກຕາຕະລາງ currencies', function () {
        $money = (new MoneyMinor)->get($this->model, 'amount_minor', 12_500_000, [
            'currency_code' => 'LAK',
        ]);

        expect($money)->toBeInstanceOf(Money::class)
            ->and($money->minor)->toBe(12_500_000)
            ->and($money->currency)->toBe('LAK')
            ->and($money->decimals)->toBe(0)          // ກີບບໍ່ມີຫົວໜ່ວຍຍ່ອຍ
            ->and($money->toDecimal())->toBe('12500000');
    });

    it('ໃຫ້ USD ໄດ້ 2 ຕຳແໜ່ງ', function () {
        $money = (new MoneyMinor)->get($this->model, 'amount_usd_minor', 57405, [
            'currency_code' => 'USD',
        ]);

        expect($money->decimals)->toBe(2)
            ->and($money->toDecimal())->toBe('574.05');
    });

    it('ຄືນ null ເມື່ອຄ່າເປັນ null', function () {
        expect((new MoneyMinor)->get($this->model, 'amount_minor', null, []))->toBeNull();
    });

    it('ໃຊ້ USD ເປັນຄ່າຕັ້ງຕົ້ນເມື່ອບໍ່ມີຄໍລຳສະກຸນ', function () {
        $money = (new MoneyMinor)->get($this->model, 'amount_usd_minor', 1234, []);

        expect($money->currency)->toBe('USD')
            ->and($money->decimals)->toBe(2);
    });

    it('ອ່ານສະກຸນຈາກຄໍລຳທີ່ກຳນົດເອງ', function () {
        $money = (new MoneyMinor('budget_currency_code'))->get($this->model, 'budget_amount_minor', 500, [
            'budget_currency_code' => 'THB',
            'currency_code' => 'LAK',   // ຄໍລຳນີ້ຕ້ອງຖືກເມີນເສີຍ
        ]);

        expect($money->currency)->toBe('THB')
            ->and($money->decimals)->toBe(2)
            ->and($money->toDecimal())->toBe('5.00');
    });

    it('ຮັບຄ່າ string ຈາກ driver ທີ່ຄືນ BIGINT ເປັນ string ໄດ້', function () {
        $money = (new MoneyMinor)->get($this->model, 'amount_minor', '12500000', [
            'currency_code' => 'LAK',
        ]);

        expect($money->minor)->toBe(12_500_000);
    });

    it('ໂຍນ error ເມື່ອສະກຸນບໍ່ມີໃນຕາຕະລາງ', function () {
        expect(fn () => (new MoneyMinor)->get($this->model, 'amount_minor', 1, [
            'currency_code' => 'XXX',
        ]))->toThrow(InvalidArgumentException::class, 'Unknown currency code: XXX');
    });
});

describe('set', function () {
    it('ເກັບ Money ເປັນ minor units', function () {
        expect((new MoneyMinor)->set($this->model, 'amount_minor', new Money(12_500_000, 'LAK', 0), []))
            ->toBe(['amount_minor' => 12_500_000]);
    });

    it('ຮັບ int ໂດຍກົງ (ຖືວ່າເປັນ minor ແລ້ວ)', function () {
        expect((new MoneyMinor)->set($this->model, 'amount_minor', 57405, []))
            ->toBe(['amount_minor' => 57405]);
    });

    it('ຍອມຮັບ null', function () {
        expect((new MoneyMinor)->set($this->model, 'amount_minor', null, []))
            ->toBe(['amount_minor' => null]);
    });

    it('ປະຕິເສດ float — ນີ້ຄືກົດຫຼັກຂອງລະບົບ', function () {
        expect(fn () => (new MoneyMinor)->set($this->model, 'amount_minor', 12500000.0, []))
            ->toThrow(InvalidArgumentException::class, 'must be Money or int minor units, float given');
    });

    it('ປະຕິເສດ string ທີ່ຄົນອາດສົ່ງມາຈາກ request', function () {
        expect(fn () => (new MoneyMinor)->set($this->model, 'amount_minor', '12500000', []))
            ->toThrow(InvalidArgumentException::class, 'must be Money or int minor units, string given');
    });
});

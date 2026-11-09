<?php

declare(strict_types=1);

use App\Domain\Money\Money;

describe('fromDecimal', function () {
    it('ອ່ານຄ່າກີບທີ່ມີ comma ໂດຍບໍ່ມີຫົວໜ່ວຍຍ່ອຍ', function () {
        $money = Money::fromDecimal('12,500,000', 'LAK', 0);

        expect($money->minor)->toBe(12_500_000)
            ->and($money->currency)->toBe('LAK')
            ->and($money->decimals)->toBe(0);
    });

    it('ອ່ານຄ່າທີ່ມີ space ຄັ່ນ', function () {
        expect(Money::fromDecimal('1 943 980', 'THB', 2)->minor)->toBe(194_398_000);
    });

    it('ແປງທົດສະນິຍົມເປັນ cents', function () {
        expect(Money::fromDecimal('12.34', 'USD', 2)->minor)->toBe(1234);
    });

    it('ບໍ່ຕົກຫຼົ່ນຈາກ float precision ແບບ (int) (8.29 * 100)', function () {
        // (int) (8.29 * 100) = 828 ໃນ PHP — ນີ້ຄືປະເພດ bug ທີ່ Money ມີໜ້າທີ່ກັນ
        expect((int) (8.29 * 100))->toBe(828)
            ->and(Money::fromDecimal('8.29', 'USD', 2)->minor)->toBe(829);
    });

    it('ປັດເສດເມື່ອທົດສະນິຍົມເກີນຫົວໜ່ວຍຍ່ອຍ', function (string $input, int $expected) {
        expect(Money::fromDecimal($input, 'USD', 2)->minor)->toBe($expected);
    })->with([
        ['12.344', 1234],
        ['12.345', 1235],   // ປັດຂຶ້ນ
        ['12.346', 1235],
    ]);

    it('ປັດເສດທົດສະນິຍົມຂອງກີບຖິ້ມ (ກີບບໍ່ມີຫົວໜ່ວຍຍ່ອຍ)', function () {
        // 6,559,489,394.82 ຄື float rounding error ຈາກ Excel — ເບິ່ງ docs/architecture.md ພາກ 1.4
        expect(Money::fromDecimal('6,559,489,394.82', 'LAK', 0)->minor)->toBe(6_559_489_395);
    });

    it('ຮັບ int ແລະ float ໄດ້ ບໍ່ແມ່ນແຕ່ string', function () {
        expect(Money::fromDecimal(12, 'USD', 2)->minor)->toBe(1200)
            ->and(Money::fromDecimal(12.34, 'USD', 2)->minor)->toBe(1234);
    });

    it('ຮັກສາເຄື່ອງໝາຍລົບ', function () {
        expect(Money::fromDecimal('-12.34', 'USD', 2)->minor)->toBe(-1234);
    });

    it('ປ່ຽນລະຫັດສະກຸນເປັນຕົວພິມໃຫຍ່', function () {
        expect(Money::fromDecimal('1', 'lak', 0)->currency)->toBe('LAK');
    });

    it('ປະຕິເສດຄ່າທີ່ bcmath ໃຊ້ບໍ່ໄດ້ ແທນທີ່ຈະປ່ອຍ ValueError ອອກໄປ', function (string $input) {
        expect(fn () => Money::fromDecimal($input, 'USD', 2))
            ->toThrow(InvalidArgumentException::class, 'Money amount must be a plain decimal number');
    })->with([
        'ຕົວອັກສອນ' => ['abc'],
        'ສັນຍາລັກສະກຸນ' => ['$12.34'],
        'ວິທະຍາສາດ' => ['1e5'],       // is_numeric() ຜ່ານ ແຕ່ bcmath ປະຕິເສດ
        'ບໍ່ມີເສດ' => ['12.'],
        'ຈຸດສອງເທື່ອ' => ['12.34.56'],
        'ຫວ່າງເປົ່າ' => [''],
    ]);
});

describe('toDecimal', function () {
    it('ຄືນຄ່າກີບເປັນຈຳນວນເຕັມ', function () {
        expect((new Money(12_500_000, 'LAK', 0))->toDecimal())->toBe('12500000');
    });

    it('ຄືນຄ່າ USD ພ້ອມ 2 ຕຳແໜ່ງ', function (int $minor, string $expected) {
        expect((new Money($minor, 'USD', 2))->toDecimal())->toBe($expected);
    })->with([
        [1234, '12.34'],
        [5, '0.05'],
        [0, '0.00'],
        [-1234, '-12.34'],
        [57405, '574.05'],
    ]);

    it('ໄປກັບໄດ້ຄົບຮອບ (round-trip)', function (string $amount, string $currency, int $decimals) {
        expect(Money::fromDecimal($amount, $currency, $decimals)->toDecimal())->toBe($amount);
    })->with([
        ['574.05', 'USD', 2],
        ['12500000', 'LAK', 0],
        ['2265.00', 'THB', 2],
    ]);
});

describe('isZero', function () {
    it('ຮູ້ວ່າຍອດເປັນສູນ', function () {
        expect((new Money(0, 'USD', 2))->isZero())->toBeTrue()
            ->and((new Money(1, 'USD', 2))->isZero())->toBeFalse();
    });
});

describe('add', function () {
    it('ບວກສະກຸນດຽວກັນ ແລະ ຄືນ instance ໃໝ່ (immutable)', function () {
        $a = new Money(1234, 'USD', 2);
        $b = new Money(766, 'USD', 2);

        $sum = $a->add($b);

        expect($sum->minor)->toBe(2000)
            ->and($sum)->not->toBe($a)
            ->and($a->minor)->toBe(1234);   // ຕົວເດີມບໍ່ຖືກແກ້
    });

    it('ບວກຍອດກີບໃຫຍ່ໄດ້ໂດຍບໍ່ເສຍຄວາມແມ່ນຍຳ', function () {
        $total = (new Money(6_559_489_394, 'LAK', 0))->add(new Money(1, 'LAK', 0));

        expect($total->minor)->toBe(6_559_489_395);
    });

    it('ປະຕິເສດການບວກຄົນລະສະກຸນ', function () {
        expect(fn () => (new Money(1, 'USD', 2))->add(new Money(1, 'LAK', 0)))
            ->toThrow(InvalidArgumentException::class, 'Currency mismatch: USD vs LAK');
    });
});

describe('constructor', function () {
    it('ປະຕິເສດລະຫັດສະກຸນທີ່ບໍ່ແມ່ນ 3 ຕົວອັກສອນ', function (string $code) {
        expect(fn () => new Money(1, $code, 2))
            ->toThrow(InvalidArgumentException::class, "Invalid currency code: {$code}");
    })->with(['US', 'USDD', '']);
});

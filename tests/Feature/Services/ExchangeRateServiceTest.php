<?php

declare(strict_types=1);

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->service = app(ExchangeRateService::class);

    $this->seedRate = fn (string $currency, string $date, string $rate) => ExchangeRate::query()->create([
        'currency_code' => $currency,
        'effective_date' => $date,
        'rate_per_usd' => $rate,
        'source' => 'manual',
    ]);
});

describe('rateFor', function () {
    it('ຄືນ 1 ສຳລັບ USD ໂດຍບໍ່ຕີ DB', function () {
        expect($this->service->rateFor('usd', Carbon::parse('2026-08-21')))->toBe('1.0000000000');
    });

    it('ຫາ rate ຂອງມື້ດຽວກັນທີ່ຫາກໍປ້ອນໄດ້', function () {
        // regression: cast `date` ຂອງ Laravel ເກັບເປັນ "2026-08-21 00:00:00" ໃນ SQLite
        // ເຮັດໃຫ້ WHERE effective_date <= '2026-08-21' ບໍ່ພົບແຖວຂອງມື້ນັ້ນ.
        // ເບິ່ງ App\Casts\DateOnly
        ($this->seedRate)('LAK', '2026-08-21', '21760');

        expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-21')))->toBe(21_760.0);
    });

    it('ເກັບວັນທີເປັນ YYYY-MM-DD ບໍ່ມີເວລາຕິດມາ', function () {
        ($this->seedRate)('LAK', '2026-08-21', '21760');

        expect(DB::table('exchange_rates')->value('effective_date'))->toBe('2026-08-21');
    });

    it('ຖອຍຫຼັງໄປຫາ rate ຫຼ້າສຸດກ່ອນວັນທີນັ້ນ', function () {
        ($this->seedRate)('LAK', '2026-01-01', '21000');
        ($this->seedRate)('LAK', '2026-06-01', '21760');

        expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-21')))->toBe(21_760.0);
    });

    it('ບໍ່ເອົາ rate ຂອງອະນາຄົດ — ຍອດຍ້ອນຫຼັງຕ້ອງບໍ່ປ່ຽນ', function () {
        ($this->seedRate)('LAK', '2026-01-01', '21000');
        ($this->seedRate)('LAK', '2026-06-01', '21760');

        expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-03-15')))->toBe(21_000.0);
    });

    it('ໂຍນ error ເມື່ອບໍ່ມີ rate ກ່ອນວັນທີນັ້ນເລີຍ', function () {
        ($this->seedRate)('LAK', '2026-06-01', '21760');

        expect(fn () => $this->service->rateFor('LAK', Carbon::parse('2026-01-01')))
            ->toThrow(RuntimeException::class, 'ບໍ່ພົບອັດຕາແລກປ່ຽນ LAK');
    });

    describe('cache', function () {
        it('ຕັ້ງອັດຕາຂອງມື້ນີ້ແລ້ວເຫັນຜົນທັນທີ ບໍ່ຕ້ອງລໍ cache ໝົດອາຍຸ', function () {
            // ນີ້ຄືສະຖານະການປະຈຳວັນ: ມີຄົນເປີດຟອມຕອນເຊົ້າ (cache ຈື່ອັດຕາຂອງມື້ວານ)
            // ແລ້ວບັນຊີຈຶ່ງມາຕັ້ງອັດຕາຂອງມື້ນີ້. ບໍ່ມີ token ໃນ key ຄົນທັງໝົດຈະຍັງ
            // ໄດ້ອັດຕາເກົ່າອີກ 6 ຊົ່ວໂມງ.
            ($this->seedRate)('LAK', '2026-08-30', '21760');

            expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-31')))->toBe(21_760.0);

            ($this->seedRate)('LAK', '2026-08-31', '22000');

            expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-31')))->toBe(22_000.0);
        });

        it('ແກ້ອັດຕາທີ່ພິມຜິດແລ້ວເຫັນຜົນທັນທີ', function () {
            $rate = ($this->seedRate)('LAK', '2026-08-31', '2117');   // ພິມຂາດເລກ

            expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-31')))->toBe(2_117.0);

            $rate->update(['rate_per_usd' => '21170']);

            expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-31')))->toBe(21_170.0);
        });

        it('ແກ້ອັດຕາໃນ millisecond ດຽວກັນກັບຕອນສ້າງ ກໍຍັງເຫັນຜົນ', function () {
            // regression: ໂທເຄັນ cache ເຄີຍເປັນ now()->getTimestampMs() ຈຶ່ງ
            // ບໍ່ປ່ຽນເມື່ອສອງການບັນທຶກຕົກໃນ millisecond ດຽວກັນ → ອັດຕາເກົ່າ
            // ຖືກສົ່ງຕໍ່ອີກ 6 ຊົ່ວໂມງ. ຕັ້ງເວລາຄ້າງໄວ້ເພື່ອບັງຄັບໃຫ້ເກີດແນ່ນອນ
            // ແທນທີ່ຈະລໍໃຫ້ເຄື່ອງໄວພໍ (ເປັນສາເຫດຂອງ test ທີ່ລົ້ມແບບສຸ່ມ).
            Carbon::setTestNow(Carbon::parse('2026-08-31 09:00:00.123'));

            $rate = ($this->seedRate)('LAK', '2026-08-31', '2117');

            expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-31')))->toBe(2_117.0);

            $rate->update(['rate_per_usd' => '21170']);

            expect((float) $this->service->rateFor('LAK', Carbon::parse('2026-08-31')))->toBe(21_170.0);

            Carbon::setTestNow();
        });

        it('ອັດຕາຂອງສະກຸນອື່ນບໍ່ຖືກກະທົບ', function () {
            ($this->seedRate)('LAK', '2026-08-31', '21760');
            ($this->seedRate)('THB', '2026-08-31', '32.22');

            expect((float) $this->service->rateFor('THB', Carbon::parse('2026-08-31')))->toBe(32.22);

            ($this->seedRate)('LAK', '2026-09-01', '22000');

            expect((float) $this->service->rateFor('THB', Carbon::parse('2026-08-31')))->toBe(32.22);
        });
    });
});

describe('sanityCheck', function () {
    it('ຈັບຂໍ້ຜິດພາດ rate 2117 ທີ່ຄວນເປັນ 21170', function () {
        // ນີ້ຄືຂໍ້ຜິດພາດຈິງໃນ Payment_Solar_3_MW.xlsx ທີ່ເຮັດໃຫ້ຍອດຜິດ 5,314 USD
        $result = $this->service->sanityCheck('LAK', '2117', Carbon::parse('2026-02-18'));

        expect($result['ok'])->toBeFalse()
            ->and($result['message'])->toContain('ຢູ່ນອກຂອບເຂດປົກກະຕິຂອງ LAK');
    });

    it('ຜ່ານເມື່ອ rate ຢູ່ໃນຂອບເຂດ ແລະ ໃກ້ rate ອ້າງອີງ', function () {
        ($this->seedRate)('LAK', '2026-01-01', '21760');

        expect($this->service->sanityCheck('LAK', '21500', Carbon::parse('2026-02-18')))
            ->toBe(['ok' => true, 'message' => null]);
    });

    it('ເຕືອນເມື່ອຫ່າງຈາກ rate ອ້າງອີງເກີນ 10% ເຖິງວ່າຈະຢູ່ໃນຂອບເຂດ', function () {
        ($this->seedRate)('LAK', '2026-01-01', '21760');

        $result = $this->service->sanityCheck('LAK', '25000', Carbon::parse('2026-02-18'));

        expect($result['ok'])->toBeFalse()
            ->and($result['message'])->toContain('ຕ່າງຈາກອັດຕາອ້າງອີງ');
    });

    it('ຜ່ານດ້ວຍ bounds ຢ່າງດຽວເມື່ອຍັງບໍ່ມີ rate ອ້າງອີງ', function () {
        expect($this->service->sanityCheck('THB', '32.22', Carbon::parse('2026-02-18')))
            ->toBe(['ok' => true, 'message' => null]);
    });

    it('ຂ້າມສະກຸນທີ່ບໍ່ໄດ້ຕັ້ງຂອບເຂດໄວ້', function () {
        expect($this->service->sanityCheck('EUR', '0.0001', Carbon::parse('2026-02-18')))
            ->toBe(['ok' => true, 'message' => null]);
    });
});

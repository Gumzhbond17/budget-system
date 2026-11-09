<?php

declare(strict_types=1);

use App\Models\ExchangeRate;
use App\Models\Project;
use App\Services\ExchangeRateService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/*
 * ອັດຕາຕັ້ງເປັນກີບແບບປ້າຍ BCEL: "1 USD = 22,239 ກີບ", "1 ບາດ = 675.48 ກີບ".
 * ສິ່ງທີ່ເກັບ ແລະ snapshot ຍັງເປັນ "ຈຳນວນສະກຸນຕໍ່ 1 USD" (CLAUDE.md).
 */

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->project = Project::factory()->create();
    $this->accountant = memberOf($this->project, 'modify');

    $this->setRate = fn (string $currency, string $date, string $rateLak) => $this->postJson('/api/v1/exchange-rates', [
        'currency_code' => $currency,
        'effective_date' => $date,
        'rate_lak' => $rateLak,
    ]);

    Sanctum::actingAs($this->accountant);
});

describe('ໜ້າຕັ້ງອັດຕາ (rate_lak)', function () {
    it('ແຖວ LAK = 1 USD ເທົ່າກັບຈັກກີບ — ເກັບເປັນ rate_per_usd ໂດຍກົງ', function () {
        ($this->setRate)('LAK', '2026-08-31', '22,239')
            ->assertCreated()
            ->assertJsonPath('data.rate_per_usd', '22239.0000000000')
            ->assertJsonPath('data.rate_lak', '22239.0000000000')
            ->assertJsonPath('data.quote_code', 'USD');

        expect(ExchangeRate::query()->value('rate_lak'))->toBeNull();
    });

    it('ແຖວ THB = 1 ບາດ ເທົ່າກັບຈັກກີບ — ແປງເປັນ "ບາດຕໍ່ 1 USD" ດ້ວຍອັດຕາ LAK', function () {
        ($this->setRate)('LAK', '2026-08-31', '22239')->assertCreated();

        $response = ($this->setRate)('THB', '2026-08-31', '675.48')
            ->assertCreated()
            ->assertJsonPath('data.rate_lak', '675.4800000000')
            ->assertJsonPath('data.quote_code', 'THB');

        // 22239 ÷ 675.48
        expect($response->json('data.rate_per_usd'))->toStartWith('32.9232');
    });

    it('ຕັ້ງອັດຕາບາດກ່ອນມີອັດຕາ USD ບໍ່ໄດ້ — ບອກໃຫ້ຕັ້ງ USD ກ່ອນ', function () {
        ($this->setRate)('THB', '2026-08-31', '675.48')
            ->assertStatus(422)
            ->assertJsonValidationErrors('rate_lak');
    });

    it('ຕ້ອງມີອັດຕາ ແລະ ສົ່ງສອງແບບພ້ອມກັນບໍ່ໄດ້', function () {
        $this->postJson('/api/v1/exchange-rates', [
            'currency_code' => 'LAK', 'effective_date' => '2026-08-31',
        ])->assertStatus(422)->assertJsonValidationErrors('rate_lak');

        $this->postJson('/api/v1/exchange-rates', [
            'currency_code' => 'LAK', 'effective_date' => '2026-08-31',
            'rate_lak' => '22239', 'rate_per_usd' => '22239',
        ])->assertStatus(422)->assertJsonValidationErrors('rate_lak');
    });

    it('ຕັ້ງອັດຕາໃຫ້ USD ບໍ່ໄດ້ — ອັດຕາ USD ເປັນກີບຢູ່ໃນແຖວ LAK', function () {
        ($this->setRate)('USD', '2026-08-31', '22239')
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency_code');
    });

    it('ແກ້ອັດຕາບາດ ໂດຍ rate_per_usd ຄິດຄືນຕາມ', function () {
        ($this->setRate)('LAK', '2026-08-31', '22239')->assertCreated();
        $id = ($this->setRate)('THB', '2026-08-31', '675.48')->json('data.id');

        $this->patchJson("/api/v1/exchange-rates/{$id}", ['rate_lak' => '700'])
            ->assertOk()
            ->assertJsonPath('data.rate_lak', '700.0000000000');

        // 22239 ÷ 700 = 31.77
        expect(ExchangeRate::query()->find($id)->rate_per_usd)->toBe('31.7700000000');
    });
});

describe('rateFor ກັບອັດຕາທີ່ຕັ້ງເປັນກີບ', function () {
    it('1 ບາດ = 675.48 ກີບ ຄົງຢູ່ເມື່ອອັດຕາ USD ປ່ຽນ', function () {
        ($this->setRate)('LAK', '2026-08-31', '22239')->assertCreated();
        ($this->setRate)('THB', '2026-08-31', '675.48')->assertCreated();
        ($this->setRate)('LAK', '2026-09-15', '22500')->assertCreated();

        $fx = app(ExchangeRateService::class);
        $date = Carbon::parse('2026-09-20');

        // ບາດຕໍ່ 1 USD ໃຊ້ອັດຕາ USD ຂອງວັນລາຍການ: 22500 ÷ 675.48
        expect($fx->rateFor('THB', $date))->toStartWith('33.3096')
            ->and($fx->lakQuoteFor('THB', $date))->toBe('675.4800000000')
            ->and($fx->lakQuoteFor('LAK', $date))->toBe('22500.0000000000');
    });

    it('lookup ສົ່ງອັດຕາເປັນກີບ ແລະ ໜ່ວຍທີ່ມັນອ້າງເຖິງ', function () {
        ($this->setRate)('LAK', '2026-08-31', '22239')->assertCreated();
        ($this->setRate)('THB', '2026-08-31', '675.48')->assertCreated();

        $this->getJson('/api/v1/exchange-rates/lookup?currency=THB&date=2026-09-01')
            ->assertOk()
            ->assertJsonPath('rate_lak', '675.4800000000')
            ->assertJsonPath('quote_code', 'THB')
            ->assertJsonPath('effective_date', '2026-08-31');

        $this->getJson('/api/v1/exchange-rates/lookup?currency=LAK&date=2026-09-01')
            ->assertOk()
            ->assertJsonPath('rate_lak', '22239.0000000000')
            ->assertJsonPath('quote_code', 'USD');
    });

    it('ແຖວເກົ່າທີ່ຕັ້ງເປັນ "ຕໍ່ 1 USD" ຍັງໃຊ້ຄ່າເດີມ', function () {
        seedBaseRates('2025-01-01');   // LAK 21760, THB 32.22 — ບໍ່ມີ rate_lak

        expect(app(ExchangeRateService::class)->rateFor('THB', Carbon::parse('2026-01-01')))
            ->toBe('32.2200000000');
    });
});

describe('ກວດອັດຕາເປັນກີບ (check?quote=lak)', function () {
    beforeEach(function () {
        ($this->setRate)('LAK', '2026-08-31', '22239')->assertCreated();
        ($this->setRate)('THB', '2026-08-31', '675.48')->assertCreated();
    });

    it('ຈັບການພິມ "ບາດຕໍ່ 1 USD" ໃສ່ຊ່ອງທີ່ຕ້ອງການ "ກີບຕໍ່ 1 ບາດ"', function () {
        // ຄວາມຜິດພາດທິດທາງແບບນີ້ເຄີຍເກີດແທ້ (2026-09)
        $this->postJson('/api/v1/exchange-rates/check', [
            'currency' => 'THB', 'rate' => '32.92', 'date' => '2026-09-01', 'quote' => 'lak',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false);
    });

    it('ຜ່ານເມື່ອອັດຕາບາດເປັນກີບສົມເຫດສົມຜົນ', function () {
        $this->postJson('/api/v1/exchange-rates/check', [
            'currency' => 'THB', 'rate' => '680', 'date' => '2026-09-01', 'quote' => 'lak',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    });
});

describe('ລາຍຈ່າຍທີ່ສົ່ງອັດຕາເປັນກີບ (fx_rate_lak)', function () {
    beforeEach(function () {
        ($this->setRate)('LAK', '2026-08-01', '22239')->assertCreated();
        ($this->setRate)('THB', '2026-08-01', '675.48')->assertCreated();

        $this->expense = fn (array $overrides) => $this->postJson("/api/v1/projects/{$this->project->uuid}/expenses", [
            'expense_date' => '2026-08-21',
            'title' => 'ຄ່າອຸປະກອນຈາກໄທ',
            'currency_code' => 'THB',
            'amount' => '1,000',
            ...$overrides,
        ]);
    });

    it('1,000 ບາດ ທີ່ 680 ກີບ/ບາດ = 680,000 ກີບ ພໍດີ', function () {
        ($this->expense)(['fx_rate_lak' => '680'])
            ->assertCreated()
            ->assertJsonPath('data.amount_lak.minor', 680_000)
            // 680,000 ÷ 22,239 = 30.5769 USD
            ->assertJsonPath('data.amount_usd.minor', 3_058)
            ->assertJsonPath('data.fx_rate_lak', '680.000000');
    });

    it('ລາຍການກີບ: fx_rate_lak ຄືກີບຕໍ່ 1 USD', function () {
        ($this->expense)(['currency_code' => 'LAK', 'amount' => '12,500,000', 'fx_rate_lak' => '22,239'])
            ->assertCreated()
            ->assertJsonPath('data.fx_rate_per_usd', '22239.0000000000')
            ->assertJsonPath('data.fx_rate_lak', '22239.000000');
    });

    it('ປະຕິເສດອັດຕາບາດທີ່ພິມຜິດທິດ ເວັ້ນແຕ່ຢືນຢັນ', function () {
        ($this->expense)(['fx_rate_lak' => '32.92'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fx_rate_lak');
    });

    it('ສົ່ງ fx_rate_lak ແລະ fx_rate_per_usd ພ້ອມກັນບໍ່ໄດ້', function () {
        ($this->expense)(['fx_rate_lak' => '680', 'fx_rate_per_usd' => '32.7'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fx_rate_lak');
    });

    it('ແກ້ຫົວຂໍ້ໂດຍບໍ່ສົ່ງອັດຕາ → snapshot ເດີມບໍ່ຂະຍັບ ເຖິງວ່າອັດຕາ USD ຈະຖືກແກ້', function () {
        $uuid = ($this->expense)(['fx_rate_lak' => '680'])->json('data.uuid');

        ExchangeRate::query()->where('currency_code', 'LAK')->first()->update(['rate_per_usd' => '23000']);

        $this->patchJson("/api/v1/expenses/{$uuid}", ['title' => 'ແກ້ຫົວຂໍ້'])
            ->assertOk()
            ->assertJsonPath('data.amount_usd.minor', 3_058);
    });
});

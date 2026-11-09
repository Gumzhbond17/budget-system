<?php

declare(strict_types=1);

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\Payee;
use App\Models\Payer;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->project = Project::factory()->create();
    $this->accountant = memberOf($this->project, 'modify');
    $this->viewer = memberOf($this->project, 'read_only');
});

describe('currencies', function () {
    it('ຄືນສະກຸນພ້ອມ decimal_places ໃຫ້ Vue format ຕົວເລກ', function () {
        Sanctum::actingAs($this->viewer);

        $data = collect($this->getJson('/api/v1/currencies')->assertOk()->json('data'))->keyBy('code');

        expect($data)->toHaveCount(3)
            ->and($data['LAK']['decimal_places'])->toBe(0)   // ກີບບໍ່ມີຫົວໜ່ວຍຍ່ອຍ
            ->and($data['USD']['decimal_places'])->toBe(2)
            ->and($data['THB']['symbol'])->toBe('฿');
    });

    it('ບໍ່ມີທາງແກ້ decimal_places ຂອງສະກຸນທີ່ມີຢູ່ແລ້ວ', function () {
        Sanctum::actingAs($this->accountant);

        // ຖ້າແກ້ LAK ຈາກ 0 ເປັນ 2 ໄດ້ ຍອດ 12,500,000 ກີບ ຈະກາຍເປັນ 125,000 ທັນທີ
        // ທົ່ວລະບົບ ໂດຍທີ່ບໍ່ມີແຖວໃດຖືກແກ້ເລີຍ — ນີ້ຄືເຫດຜົນທີ່ຄໍລຳນີ້ຕາຍຕົວ
        $this->patchJson('/api/v1/currencies/LAK', ['decimal_places' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('decimal_places');

        expect(Currency::query()->find('LAK')->decimal_places)->toBe(0);
    });

    it('ປ່ຽນລະຫັດສະກຸນບໍ່ໄດ້ — ມີ FK ຊີ້ມາຫາ 5 ຕາຕະລາງ', function () {
        Sanctum::actingAs($this->accountant);

        $this->patchJson('/api/v1/currencies/LAK', ['code' => 'KIP'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });

    it('ສົ່ງຄ່າເກົ່າຄືນມາຜ່ານໄດ້ — ຟອມສົ່ງທັງກ້ອນ ບໍ່ແມ່ນສະເພາະຊ່ອງທີ່ແກ້', function () {
        Sanctum::actingAs($this->accountant);

        $this->patchJson('/api/v1/currencies/LAK', [
            'code' => 'LAK',
            'decimal_places' => 0,
            'name_lo' => 'ກີບລາວ',
            'name_en' => 'Lao Kip',
            'symbol' => '₭',
        ])
            ->assertOk()
            ->assertJsonPath('data.name_lo', 'ກີບລາວ');
    });

    it('ສ້າງສະກຸນໃໝ່ໄດ້ ພ້ອມກຳນົດ decimal_places ເອງ', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/currencies', [
            'code' => 'vnd',
            'name_lo' => 'ດົ່ງ',
            'name_en' => 'Vietnamese Dong',
            'symbol' => '₫',
            'decimal_places' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'VND')
            ->assertJsonPath('data.decimal_places', 0)
            ->assertJsonPath('data.usage_count', 0);

        // ຄ່າທີ່ MoneyMinor cast ອ່ານຕ້ອງກົງກັນທັນທີ ບໍ່ຕ້ອງລໍ cache ໝົດອາຍຸ
        expect(Currency::decimalsFor('VND'))->toBe(0);
    });

    it('ປະຕິເສດລະຫັດທີ່ບໍ່ແມ່ນ ISO-4217 ແລະ ລະຫັດຊ້ຳ', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/currencies', [
            'code' => 'EURO', 'name_lo' => 'ຢູໂຣ', 'name_en' => 'Euro', 'symbol' => '€', 'decimal_places' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/currencies', [
            'code' => 'LAK', 'name_lo' => 'ກີບ', 'name_en' => 'Kip', 'symbol' => '₭', 'decimal_places' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    });

    it('viewer ອ່ານໄດ້ ແຕ່ຂຽນບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/currencies')->assertOk();
        $this->postJson('/api/v1/currencies', [
            'code' => 'VND', 'name_lo' => 'ດົ່ງ', 'name_en' => 'Dong', 'symbol' => '₫', 'decimal_places' => 0,
        ])->assertForbidden();
        $this->patchJson('/api/v1/currencies/LAK', ['name_lo' => 'ອື່ນ'])->assertForbidden();
        $this->deleteJson('/api/v1/currencies/LAK')->assertForbidden();
    });

    it('ລຶບສະກຸນທີ່ບໍ່ມີໃຜອ້າງເຖິງໄດ້', function () {
        Sanctum::actingAs($this->accountant);

        Currency::query()->create([
            'code' => 'VND', 'name_lo' => 'ດົ່ງ', 'name_en' => 'Dong',
            'symbol' => '₫', 'decimal_places' => 0,
        ]);

        $this->deleteJson('/api/v1/currencies/VND')->assertNoContent();

        $this->assertDatabaseMissing('currencies', ['code' => 'VND']);
    });

    it('ລຶບບໍ່ໄດ້ເມື່ອມີແຖວອ້າງເຖິງ — ຕອບ 409 ບໍ່ແມ່ນ 500 ຈາກ FK', function () {
        Sanctum::actingAs($this->accountant);

        Currency::query()->create([
            'code' => 'VND', 'name_lo' => 'ດົ່ງ', 'name_en' => 'Dong',
            'symbol' => '₫', 'decimal_places' => 0,
        ]);

        // ອັດຕາແລກປ່ຽນກໍນັບເປັນການອ້າງອີງ — ລືມມັນແລ້ວຈະໄດ້ 500 ຈາກ FK
        ExchangeRate::query()->create([
            'currency_code' => 'VND', 'effective_date' => '2026-08-21', 'rate_per_usd' => '26000',
        ]);

        $this->deleteJson('/api/v1/currencies/VND')->assertStatus(409);

        $this->assertDatabaseHas('currencies', ['code' => 'VND']);
    });

    it('ລຶບສະກຸນຫຼັກ USD/LAK ບໍ່ໄດ້ ເຖິງແມ່ນຍັງບໍ່ມີລາຍການໃດ', function (string $code) {
        Sanctum::actingAs($this->accountant);

        // USD = pivot ຂອງທຸກ rate, LAK = ສະກຸນທີ່ CurrencyConverter ຄິດໃສ່ທຸກແຖວ
        $this->deleteJson("/api/v1/currencies/{$code}")->assertStatus(409);

        $this->assertDatabaseHas('currencies', ['code' => $code]);
    })->with(['USD', 'LAK']);

    it('ສະກຸນທີ່ຢຸດໃຊ້ບໍ່ຂຶ້ນໃນ dropdown ແຕ່ໜ້າຈັດການຍັງເຫັນ', function () {
        Sanctum::actingAs($this->accountant);

        $this->patchJson('/api/v1/currencies/THB', ['is_active' => false])->assertOk();

        $this->getJson('/api/v1/currencies')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/currencies?include_inactive=1')->assertOk()->assertJsonCount(3, 'data');
    });

    it('ຕ້ອງ login ກ່ອນ', function () {
        $this->getJson('/api/v1/currencies')->assertUnauthorized();
    });
});

describe('categories', function () {
    it('viewer ອ່ານໄດ້ ແຕ່ສ້າງບໍ່ໄດ້', function () {
        ExpenseCategory::factory()->create(['code' => 'EQUIPMENT', 'name_lo' => 'ອຸປະກອນຕ່າງໆ']);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/categories', ['code' => 'FUEL', 'name_lo' => 'ນ້ຳມັນ'])->assertForbidden();
    });

    it('accountant ສ້າງໄດ້', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/categories', [
            'code' => 'FUEL',
            'name_lo' => 'ນ້ຳມັນ',
            'color' => '#f59e0b',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'FUEL')
            ->assertJsonPath('data.color', '#f59e0b');
    });

    it('ປະຕິເສດລະຫັດຊ້ຳ ແລະ ສີທີ່ບໍ່ຖືກຮູບແບບ', function () {
        ExpenseCategory::factory()->create(['code' => 'EQUIPMENT']);

        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/categories', ['code' => 'EQUIPMENT', 'name_lo' => 'ຊ້ຳ'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/categories', ['code' => 'X', 'name_lo' => 'ສີຜິດ', 'color' => 'red'])
            ->assertStatus(422)->assertJsonValidationErrors('color');
    });

    it('ປະຕິເສດໝວດທີ່ເປັນແມ່ຂອງຕົນເອງ', function () {
        $category = ExpenseCategory::factory()->create(['code' => 'EQUIPMENT']);

        Sanctum::actingAs($this->accountant);

        $this->patchJson("/api/v1/categories/{$category->id}", ['parent_id' => $category->id])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');
    });

    it('ຄືນເປັນຕົ້ນໄມ້ເມື່ອຂໍ tree=1', function () {
        $parent = ExpenseCategory::factory()->create(['code' => 'DOCS', 'name_lo' => 'ເອກະສານ']);
        ExpenseCategory::factory()->child($parent)->create(['code' => 'EDL', 'name_lo' => 'EDL']);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/categories?tree=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.children.0.name_lo', 'EDL');
    });

    it('ເຊື່ອງໝວດທີ່ຢຸດໃຊ້ແລ້ວ ນອກຈາກຈະຂໍມາ', function () {
        ExpenseCategory::factory()->create(['code' => 'OLD', 'is_active' => false]);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/categories')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/categories?include_inactive=1')->assertJsonCount(1, 'data');
    });

    it('ຄົ້ນຫາຕາມລະຫັດ ຫຼື ຊື່ ທັງສອງພາສາ', function () {
        ExpenseCategory::factory()->create(['code' => 'FUEL', 'name_lo' => 'ນ້ຳມັນ', 'name_en' => 'Fuel']);
        ExpenseCategory::factory()->create(['code' => 'LABOR', 'name_lo' => 'ຄ່າແຮງ', 'name_en' => 'Labor']);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/categories?search=FUEL')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/categories?search=ຄ່າແຮງ')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/categories?search=Labor')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/categories?search=ບໍ່ມີ')->assertJsonCount(0, 'data');
    });

    it('ນັບລາຍຈ່າຍ ແລະ ໝວດລູກ ໃຫ້ໜ້າຈໍຮູ້ວ່າລຶບໄດ້ບໍ', function () {
        $parent = ExpenseCategory::factory()->create(['code' => 'DOCS']);
        ExpenseCategory::factory()->child($parent)->create(['code' => 'EDL']);

        Sanctum::actingAs($this->viewer);

        $rows = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))->keyBy('code');

        expect($rows['DOCS']['children_count'])->toBe(1)
            ->and($rows['DOCS']['expenses_count'])->toBe(0)
            ->and($rows['EDL']['children_count'])->toBe(0);
    });
});

describe('ລຶບໝວດລາຍຈ່າຍ', function () {
    it('ລຶບໝວດທີ່ບໍ່ມີໃຜອ້າງເຖິງໄດ້', function () {
        // ໝວດທີ່ຫາກໍສ້າງແລ້ວພິມລະຫັດຜິດ — ບໍ່ມີປະຫວັດຫຍັງໃຫ້ເສຍ
        $category = ExpenseCategory::factory()->create(['code' => 'TYPO']);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();

        expect(ExpenseCategory::query()->find($category->id))->toBeNull();
    });

    it('ໝວດທີ່ມີລາຍຈ່າຍຢູ່ລຶບບໍ່ໄດ້ — 409 ບໍ່ແມ່ນລຶບປະຫວັດຖິ້ມ', function () {
        // FK ເປັນ nullOnDelete: ຖ້າປ່ອຍໃຫ້ລຶບ ລາຍຈ່າຍຈະກາຍເປັນ "ບໍ່ມີໝວດ" ງຽບໆ
        seedBaseRates();
        $category = ExpenseCategory::factory()->create(['code' => 'FUEL']);
        createExpense($this->project, $this->accountant, ['category_id' => $category->id]);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertStatus(409);

        expect(ExpenseCategory::query()->find($category->id))->not->toBeNull();
    });

    it('ໝວດແມ່ທີ່ຍັງມີລູກລຶບບໍ່ໄດ້', function () {
        $parent = ExpenseCategory::factory()->create(['code' => 'DOCS']);
        ExpenseCategory::factory()->child($parent)->create(['code' => 'EDL']);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/categories/{$parent->id}")->assertStatus(409);
    });

    it('viewer ລຶບບໍ່ໄດ້', function () {
        $category = ExpenseCategory::factory()->create(['code' => 'TYPO']);

        Sanctum::actingAs($this->viewer);

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertForbidden();
    });
});

describe('payers', function () {
    it('ຕັດ space ຫົວທ້າຍ ແລະ ກັນຊື່ຊ້ຳ — ບັນຫາ "SSS" vs "SSS " ຂອງ Excel', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/payers', ['name' => 'SSS'])->assertCreated();

        $this->postJson('/api/v1/payers', ['name' => '  SSS  '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        expect(Payer::query()->count())->toBe(1);
    });

    it('ຢຸດໃຊ້ດ້ວຍ is_active ໄດ້', function () {
        $payer = Payer::factory()->create(['name' => 'Sinexay']);

        Sanctum::actingAs($this->accountant);

        $this->patchJson("/api/v1/payers/{$payer->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    });

    it('ຄົ້ນຫາຕາມຊື່ ຫຼື ເບີໂທ', function () {
        Payer::factory()->create(['name' => 'Sinexay', 'contact_phone' => '02055512345']);
        Payer::factory()->create(['name' => 'SSS']);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/payers?search=Sine')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/payers?search=0205551')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/payers?search=ບໍ່ມີ')->assertJsonCount(0, 'data');
    });

    it('ລຶບໄດ້ເມື່ອບໍ່ມີໃຜອ້າງເຖິງ', function () {
        $payer = Payer::factory()->create(['name' => 'ພິມຜິດ']);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/payers/{$payer->id}")->assertNoContent();

        expect(Payer::query()->find($payer->id))->toBeNull();
    });

    it('ລຶບບໍ່ໄດ້ເມື່ອມີລາຍຮັບອ້າງເຖິງ — 409 ບໍ່ແມ່ນລຶບປະຫວັດຖິ້ມ', function () {
        // revenues.payer_id ເປັນ nullOnDelete: ຖ້າປ່ອຍໃຫ້ລຶບ ລາຍຮັບຈະກາຍເປັນ
        // "ບໍ່ຮູ້ວ່າໃຜສົ່ງເງິນມາ" ງຽບໆ
        seedBaseRates();
        $payer = Payer::factory()->create(['name' => 'SSS']);
        createRevenue($this->project, $this->accountant, ['payer_id' => $payer->id]);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/payers/{$payer->id}")->assertStatus(409);

        expect(Payer::query()->find($payer->id))->not->toBeNull();
    });

    it('viewer ລຶບບໍ່ໄດ້', function () {
        $payer = Payer::factory()->create(['name' => 'ພິມຜິດ']);

        Sanctum::actingAs($this->viewer);

        $this->deleteJson("/api/v1/payers/{$payer->id}")->assertForbidden();
    });
});

describe('payees', function () {
    it('ຮັບເລກບັນຊີເຂົ້າ ແຕ່ຄືນອອກສະເພາະທີ່ mask ແລ້ວ', function () {
        Sanctum::actingAs($this->accountant);

        $response = $this->postJson('/api/v1/payees', [
            'name' => 'ຮ້ານຫລຽນໄຊ',
            'bank_name' => 'BCEL',
            'bank_account_no' => '0101234567890',
        ])->assertCreated();

        $response->assertJsonPath('data.bank_account_no_masked', '•••••••••7890')
            ->assertJsonMissingPath('data.bank_account_no');

        expect($response->getContent())->not->toContain('0101234567890')
            ->and(Payee::query()->sole()->bank_account_no)->toBe('0101234567890');
    });

    it('ຄົ້ນຫາດ້ວຍຊື່ພາສາລາວ', function () {
        Payee::factory()->create(['name' => 'ຮ້ານຫລຽນໄຊ']);
        Payee::factory()->create(['name' => 'ນາງ ບຸດສະບາ']);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/payees?search=ບຸດສະບາ')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'ນາງ ບຸດສະບາ');
    });

    it('ຄືນຈຳນວນລາຍຈ່າຍໃຫ້ໜ້າຈໍຮູ້ວ່າລຶບໄດ້ບໍ', function () {
        Payee::factory()->create(['name' => 'ຮ້ານຫລຽນໄຊ']);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/payees')
            ->assertOk()
            ->assertJsonPath('data.0.expenses_count', 0);
    });

    it('ຈຳກັດ per_page ບໍ່ໃຫ້ດຶງທັງຕາຕະລາງດ້ວຍ query ດຽວ', function () {
        Payee::factory()->count(3)->create();

        Sanctum::actingAs($this->viewer);

        // ຂໍ 9999 ແຕ່ຖືກຫຼຸດເປັນ 200 — meta.per_page ເປັນຫຼັກຖານວ່າ clamp ເຮັດວຽກ
        $this->getJson('/api/v1/payees?per_page=9999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 200);
    });

    it('ລຶບໄດ້ເມື່ອບໍ່ມີລາຍຈ່າຍອ້າງເຖິງ', function () {
        $payee = Payee::factory()->create(['name' => 'ພິມຜິດ']);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/payees/{$payee->id}")->assertNoContent();

        expect(Payee::query()->find($payee->id))->toBeNull();
    });

    it('ລຶບບໍ່ໄດ້ເມື່ອມີລາຍຈ່າຍອ້າງເຖິງ', function () {
        seedBaseRates();
        $payee = Payee::factory()->create(['name' => 'ຮ້ານຫລຽນໄຊ']);
        createExpense($this->project, $this->accountant, ['payee_id' => $payee->id]);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/payees/{$payee->id}")->assertStatus(409);

        expect(Payee::query()->find($payee->id))->not->toBeNull();
    });

    it('ແກ້ໄຂໂດຍບໍ່ສົ່ງເລກບັນຊີ = ເລກເກົ່າຄົງໄວ້', function () {
        // ຟອມແກ້ໄຂເຕີມເລກເກົ່າຄືນບໍ່ໄດ້ (server ບໍ່ເຄີຍສົ່ງອອກ) ຈຶ່ງຕ້ອງແນ່ໃຈ
        // ວ່າການບັນທຶກຊື່ຢ່າງດຽວບໍ່ໄດ້ລ້າງເລກບັນຊີຖິ້ມແບບງຽບໆ
        $payee = Payee::factory()->create([
            'name' => 'ຮ້ານເກົ່າ',
            'bank_account_no' => '0101234567890',
        ]);

        Sanctum::actingAs($this->accountant);

        $this->patchJson("/api/v1/payees/{$payee->id}", ['name' => 'ຮ້ານໃໝ່'])->assertOk();

        expect($payee->fresh()->bank_account_no)->toBe('0101234567890');
    });

    it('ສົ່ງເລກບັນຊີເປັນ null = ລ້າງອອກ', function () {
        $payee = Payee::factory()->create(['bank_account_no' => '0101234567890']);

        Sanctum::actingAs($this->accountant);

        $this->patchJson("/api/v1/payees/{$payee->id}", ['bank_account_no' => null])
            ->assertOk()
            ->assertJsonPath('data.bank_account_no_masked', null);

        expect($payee->fresh()->bank_account_no)->toBeNull();
    });
});

describe('exchange-rates', function () {
    it('ສ້າງອັດຕາໄດ້ ແລະ ຄືນເປັນ string', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/exchange-rates', [
            'currency_code' => 'lak',
            'effective_date' => '2026-08-21',
            'rate_per_usd' => '21760',
        ])
            ->assertCreated()
            ->assertJsonPath('data.currency_code', 'LAK')
            ->assertJsonPath('data.rate_per_usd', '21760.0000000000');
    });

    it('ກັນອັດຕາຊ້ຳໃນວັນດຽວກັນ', function () {
        Sanctum::actingAs($this->accountant);

        $payload = ['currency_code' => 'LAK', 'effective_date' => '2026-08-21', 'rate_per_usd' => '21760'];

        $this->postJson('/api/v1/exchange-rates', $payload)->assertCreated();
        $this->postJson('/api/v1/exchange-rates', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('effective_date');
    });

    it('ຍອມໃຫ້ສະກຸນຕ່າງກັນໃຊ້ວັນທີດຽວກັນ', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/exchange-rates', [
            'currency_code' => 'LAK', 'effective_date' => '2026-08-21', 'rate_per_usd' => '21760',
        ])->assertCreated();

        $this->postJson('/api/v1/exchange-rates', [
            'currency_code' => 'THB', 'effective_date' => '2026-08-21', 'rate_per_usd' => '32.22',
        ])->assertCreated();
    });

    it('viewer ສ້າງອັດຕາບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->viewer);

        $this->postJson('/api/v1/exchange-rates', [
            'currency_code' => 'LAK', 'effective_date' => '2026-08-21', 'rate_per_usd' => '21760',
        ])->assertForbidden();
    });

    it('ຄົນທີ່ບໍ່ຢູ່ໂຄງການໃດເລີຍ ອ່ານໄດ້ ແຕ່ຂຽນບໍ່ໄດ້', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/exchange-rates')->assertOk();
        $this->postJson('/api/v1/exchange-rates', [
            'currency_code' => 'LAK', 'effective_date' => '2026-08-21', 'rate_per_usd' => '21760',
        ])->assertForbidden();
    });
});

describe('exchange-rates lookup ແລະ check (ຟອມ Vue ພາກ 7.3)', function () {
    beforeEach(function () {
        seedBaseRates('2025-01-01');
        Sanctum::actingAs($this->viewer);
    });

    it('autofill ອັດຕາຂອງວັນທີທີ່ຂໍ', function () {
        // 10 ຕຳແໜ່ງສະເໝີ ຄືກັນກັບທີ່ຄໍລຳ decimal(24,10) ເກັບ — Vue ໃສ່ຊ່ອງ input ໄດ້ໂດຍກົງ
        $this->getJson('/api/v1/exchange-rates/lookup?currency=LAK&date=2026-08-21')
            ->assertOk()
            ->assertJsonPath('rate', '21760.0000000000')
            ->assertJsonPath('currency', 'LAK');
    });

    it('USD ຄືນ 1 ໂດຍບໍ່ຕ້ອງມີແຖວໃນຕາຕະລາງ', function () {
        $this->getJson('/api/v1/exchange-rates/lookup?currency=USD&date=2026-08-21')
            ->assertOk()
            ->assertJsonPath('rate', '1.0000000000');
    });

    it('ຄືນ 404 ເມື່ອບໍ່ມີອັດຕາກ່ອນວັນທີນັ້ນ', function () {
        $this->getJson('/api/v1/exchange-rates/lookup?currency=LAK&date=2024-01-01')
            ->assertNotFound()
            ->assertJsonPath('rate', null);
    });

    it('ເຕືອນ rate 2117 ແຕ່ຍັງຕອບ 200 — ນີ້ຄືຄຳເຕືອນສົດ ບໍ່ແມ່ນການປະຕິເສດ', function () {
        $this->postJson('/api/v1/exchange-rates/check', [
            'currency' => 'LAK', 'rate' => '2117', 'date' => '2026-02-18',
        ])
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'message']);
    });

    it('ຜ່ານເມື່ອອັດຕາສົມເຫດສົມຜົນ', function () {
        $this->postJson('/api/v1/exchange-rates/check', [
            'currency' => 'LAK', 'rate' => '21500', 'date' => '2026-02-18',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', null);
    });
});

describe('budget-items', function () {
    it('accountant ສ້າງລາຍການງົບໄດ້ ແລະ ຄິດຍອດໃຫ້ເອງ', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/budget-items", [
            'name' => 'ແຜງໂຊລາ ຂະໜາດ 500 W',
            'unit' => 'pcs',
            'quantity' => '2.5',
            'currency_code' => 'USD',
            'unit_price' => '1,000.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.unit_price.minor', 100_000)
            ->assertJsonPath('data.amount.minor', 250_000)          // 2.5 × 1,000
            ->assertJsonPath('data.amount_usd.decimal', '2500.00');
    });

    it('viewer ສ້າງບໍ່ໄດ້ ແຕ່ອ່ານໄດ້', function () {
        createBudgetItem($this->project);

        Sanctum::actingAs($this->viewer);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/budget-items")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/projects/{$this->project->uuid}/budget-items", [
            'name' => 'ຫ້າມ', 'unit_price' => '1.00',
        ])->assertForbidden();
    });

    it('ຄົນນອກໂຄງການເບິ່ງງົບບໍ່ໄດ້', function () {
        Sanctum::actingAs(memberOf(Project::factory()->create(), 'full_access'));

        $this->getJson("/api/v1/projects/{$this->project->uuid}/budget-items")->assertForbidden();
    });

    it('ແກ້ແລ້ວຄິດຍອດຄືນໃໝ່', function () {
        $item = createBudgetItem($this->project, ['quantity' => '1', 'unit_price' => '1000.00']);

        Sanctum::actingAs($this->accountant);

        $this->patchJson("/api/v1/budget-items/{$item->id}", ['quantity' => '4'])
            ->assertOk()
            ->assertJsonPath('data.amount.minor', 400_000);
    });

    it('ລຶບໄດ້ — ງົບແມ່ນແຜນ ບໍ່ແມ່ນບັນທຶກການເງິນ', function () {
        $item = createBudgetItem($this->project);

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/budget-items/{$item->id}")->assertNoContent();

        $this->assertDatabaseMissing('budget_items', ['id' => $item->id]);
    });

    it('ປະຕິເສດຕົວເລກທີ່ bcmath ໃຊ້ບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/budget-items", [
            'name' => 'ຜິດ', 'unit_price' => '1e5',
        ])->assertStatus(422)->assertJsonValidationErrors('unit_price');
    });
});

it('ອັດຕາແລກປ່ຽນຍັງບໍ່ມີ route ລຶບ', function () {
    // ໝວດ/ຜູ້ຈ່າຍ/ຜູ້ຮັບເງິນ ລຶບໄດ້ເມື່ອບໍ່ມີໃຜອ້າງເຖິງ ແຕ່ rate ບໍ່ໄດ້ເລີຍ:
    // ລາຍການເກົ່າ snapshot rate ໄວ້ໃນແຖວຂອງຕົນແລ້ວ ການລຶບແຖວ rate ຈຶ່ງເປັນ
    // ການທຳລາຍຫຼັກຖານວ່າຕົວເລກມາຈາກໃສ ໂດຍບໍ່ມີສັນຍານໃຫ້ຈັບໄດ້
    $rate = ExchangeRate::query()->create([
        'currency_code' => 'LAK', 'effective_date' => '2026-01-01', 'rate_per_usd' => '21760', 'source' => 'manual',
    ]);

    Sanctum::actingAs($this->accountant);

    $this->deleteJson("/api/v1/exchange-rates/{$rate->id}")->assertMethodNotAllowed();
});

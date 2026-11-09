<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * ຮ້ານ (`type = store`) — ທາງລັດສຳລັບທຸລະກິດນ້ອຍ.
 *
 * ຄຸນສົມບັດທີ່ຕ້ອງຮັກສາ: **ໃສ່ຊື່ຮ້ານແລ້ວບັນທຶກເງິນໄດ້ເລີຍ** ໂດຍບໍ່ຕ້ອງໃຫ້
 * ຜູ້ດູແລລະບົບມາຕັ້ງໂຄງການ ແລະ ມອບສິດໃຫ້ກ່ອນ.
 *
 * ພ້ອມກັນນັ້ນ ຂອບເຂດຄວາມເສຍຫາຍຕ້ອງຢູ່ໃນຕົ້ນໄມ້ຂອງຕົນ ແລະ ຜູ້ໃຊ້ຕ້ອງ
 * ຕໍ່ອາຍຸການໃຊ້ງານໃຫ້ຕົນເອງບໍ່ໄດ້.
 */
beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->company = Company::factory()->create(['code' => 'SSS', 'name' => 'ບໍລິສັດ ແສງສະຫວ່າງ']);
    $this->owner = User::factory()->create(['company_id' => $this->company->id]);
});

describe('ສ້າງຮ້ານ', function () {
    beforeEach(fn () => Sanctum::actingAs($this->owner));

    it('ໃສ່ແຕ່ຊື່ຮ້ານກໍພໍ — ລະຫັດ ແລະ ອົງກອນແມ່ຖືກຕື່ມໃຫ້ເອງ', function () {
        $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ອອນລາຍ'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'store');

        $store = Company::query()->where('name', 'ຮ້ານ ອອນລາຍ')->sole();

        expect($store->parent_id)->toBe($this->company->id)
            // ຊື່ລາວລ້ວນຜ່ານ Str::slug ແລ້ວເຫຼືອຄ່າຫວ່າງ — ຕົກມາໃຊ້ STORE
            ->and($store->code)->toBe('STORE');
    });

    it('ຊື່ທີ່ມີຕົວລາຕິນພໍສົມຄວນ ໄດ້ລະຫັດຈາກຊື່ຈິງ', function () {
        $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ Mekong Mart'])->assertCreated();

        expect(Company::query()->where('name', 'ຮ້ານ Mekong Mart')->value('code'))
            ->toBe('MEKONGMART');
    });

    /** ຕົວອັກສອນລາຕິນຕົວດຽວບໍ່ພຽງພໍທີ່ຈະເປັນລະຫັດ — ຢ່າໃຫ້ໄດ້ລະຫັດ "A" */
    it('ຊື່ທີ່ເຫຼືອຕົວລາຕິນສັ້ນເກີນ ຕົກມາໃຊ້ STORE', function () {
        $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ອອນລາຍ A'])->assertCreated();

        expect(Company::query()->where('name', 'ຮ້ານ ອອນລາຍ A')->value('code'))
            ->toBe('STORE');
    });

    it('ຊື່ທີ່ໃຫ້ລະຫັດຊ້ຳກັນ ຖືກຕໍ່ເລກໃຫ້ ບໍ່ແມ່ນລົ້ມ', function () {
        foreach (['ຮ້ານ ໜຶ່ງ', 'ຮ້ານ ສອງ', 'ຮ້ານ ສາມ'] as $name) {
            $this->postJson('/api/v1/companies', ['name' => $name])->assertCreated();
        }

        expect(Company::query()->where('type', 'store')->pluck('code')->sort()->values()->all())
            ->toBe(['STORE', 'STORE-2', 'STORE-3']);
    });

    /** ນີ້ຄືເຫດຜົນທັງໝົດຂອງຄຸນສົມບັດນີ້ */
    it('ບັນທຶກລາຍຮັບ-ລາຍຈ່າຍໄດ້ທັນທີຫຼັງສ້າງ', function () {
        $uuid = $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ຂາຍເຄື່ອງ'])
            ->assertCreated()
            ->json('data.uuid');

        $store = Company::query()->where('uuid', $uuid)->sole();
        $project = $store->projects()->sole();

        $this->postJson("/api/v1/projects/{$project->uuid}/revenues", [
            'received_date' => '2026-09-14',
            'title' => 'ຂາຍເຄື່ອງປະຈຳວັນ',
            'currency_code' => 'USD',
            'amount' => '250.00',
        ])->assertCreated();

        $this->postJson("/api/v1/projects/{$project->uuid}/expenses", [
            'expense_date' => '2026-09-14',
            'title' => 'ຄ່າສົ່ງເຄື່ອງ',
            'currency_code' => 'LAK',
            'amount' => '150,000',
            'fx_rate_per_usd' => '21760',
        ])->assertCreated();
    });

    it('ຜູ້ສ້າງໄດ້ສິດເຕັມໃນຮ້ານຂອງຕົນ', function () {
        $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ຂອງຂ້ອຍ'])->assertCreated();

        $project = Project::query()->where('name', 'ຮ້ານ ຂອງຂ້ອຍ')->sole();

        expect($this->owner->fresh()->hasProjectRole($project->id, ['full_access']))->toBeTrue();
    });

    it('ຮ້ານປາກົດຢູ່ລາຍການອົງກອນຂອງຜູ້ສ້າງ', function () {
        $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ໃໝ່'])->assertCreated();

        $names = collect($this->getJson('/api/v1/companies')->assertOk()->json('data'))
            ->pluck('name');

        expect($names)->toContain('ບໍລິສັດ ແສງສະຫວ່າງ', 'ຮ້ານ ໃໝ່');
    });
});

describe('ຂອບເຂດຂອງຜູ້ໃຊ້ທຳມະດາ', function () {
    beforeEach(fn () => Sanctum::actingAs($this->owner));

    /** ຫົວໃຈຂອງການອະນຸຍາດໃຫ້ສ້າງເອງ — ຕໍ່ອາຍຸໃຫ້ຕົນເອງບໍ່ໄດ້ */
    it('ຕັ້ງແພັກເກັດ ຫຼື ວັນໝົດອາຍຸໃຫ້ຮ້ານຕົນເອງບໍ່ໄດ້', function () {
        $this->postJson('/api/v1/companies', [
            'name' => 'ຮ້ານ ໂລບ',
            'plan' => 'active',
            'access_expires_at' => Carbon::today()->addYears(10)->toDateString(),
        ])->assertCreated();

        $store = Company::query()->where('name', 'ຮ້ານ ໂລບ')->sole();

        expect($store->access_expires_at)->toBeNull()
            ->and($store->plan)->not->toBe('active');
    });

    it('ເອົາຮ້ານໄປແປະໃສ່ຕົ້ນໄມ້ຂອງລູກຄ້າລາຍອື່ນບໍ່ໄດ້', function () {
        $other = Company::factory()->create(['code' => 'OTHER']);

        $this->postJson('/api/v1/companies', [
            'name' => 'ຮ້ານ ແຝງ',
            'parent_uuid' => $other->uuid,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_uuid');
    });

    it('ແກ້ຮ້ານຂອງລູກຄ້າລາຍອື່ນບໍ່ໄດ້', function () {
        $other = Company::factory()->create(['code' => 'OTHER']);
        $theirStore = Company::factory()->childOf($other)->create(['type' => 'store']);

        $this->patchJson("/api/v1/companies/{$theirStore->uuid}", ['name' => 'ຖືກແກ້'])
            ->assertForbidden();
    });

    it('ປ່ຽນຊື່ ແລະ ລຶບຮ້ານຂອງຕົນໄດ້', function () {
        $uuid = $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ຊົ່ວຄາວ'])
            ->assertCreated()->json('data.uuid');

        $this->patchJson("/api/v1/companies/{$uuid}", ['name' => 'ຮ້ານ ຖາວອນ'])
            ->assertOk()
            ->assertJsonPath('data.name', 'ຮ້ານ ຖາວອນ');

        $this->deleteJson("/api/v1/companies/{$uuid}")->assertNoContent();
    });

    /** ໂຄງການເຊື່ອງຂອງຮ້ານຕ້ອງໄປພ້ອມກັນ ບໍ່ດັ່ງນັ້ນຈະຄ້າງຢູ່ຕົວເລືອກໜ້າທຸລະກຳ */
    it('ລຶບຮ້ານແລ້ວໂຄງການເຊື່ອງຂອງມັນຫາຍໄປນຳ', function () {
        $uuid = $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ປິດກິດຈະການ'])
            ->assertCreated()->json('data.uuid');

        $this->deleteJson("/api/v1/companies/{$uuid}")->assertNoContent();

        expect(Project::query()->where('name', 'ຮ້ານ ປິດກິດຈະການ')->exists())->toBeFalse()
            ->and($this->getJson('/api/v1/projects')->assertOk()->json('data'))->toBeEmpty();
    });

    it('ຮ້ານທີ່ມີເງິນເຄື່ອນໄຫວແລ້ວ ລຶບບໍ່ໄດ້', function () {
        $uuid = $this->postJson('/api/v1/companies', ['name' => 'ຮ້ານ ມີລາຍການ'])
            ->assertCreated()->json('data.uuid');

        $project = Company::query()->where('uuid', $uuid)->sole()->projects()->sole();

        createExpense($project, $this->owner);

        $this->deleteJson("/api/v1/companies/{$uuid}")->assertForbidden();
    });
});

describe('ການເກັບເງິນຢູ່ຮາກຕົ້ນໄມ້', function () {
    /**
     * ຖ້າຮ້ານມີ trial ຂອງຕົນ ມັນຈະໝົດອາຍຸໃນ 45 ວັນ ທັງທີ່ບໍລິສັດແມ່ຈ່າຍແລ້ວ
     * — ແລະ ການຕໍ່ອາຍຸຢູ່ບໍລິສັດແມ່ຈະບໍ່ປົດລັອກໃຫ້ມັນ.
     */
    it('ຮ້ານໃຊ້ແພັກເກັດ ແລະ ວັນໝົດອາຍຸຂອງບໍລິສັດແມ່', function () {
        $paid = Company::factory()->create([
            'plan' => 'active',
            'access_expires_at' => Carbon::today()->addYear(),
        ]);

        $store = Company::factory()->childOf($paid)->create([
            'type' => 'store',
            'plan' => 'trial',
            'trial_ends_at' => Carbon::yesterday(),   // ຄ່າດິບທີ່ຕ້ອງຖືກມອງຂ້າມ
        ]);

        expect($store->hasActiveAccess())->toBeTrue()
            ->and($store->effectivePlan())->toBe('active')
            ->and($store->effectiveExpiryDate()?->toDateString())
            ->toBe(Carbon::today()->addYear()->toDateString());
    });

    it('ບໍລິສັດແມ່ໝົດອາຍຸ = ຮ້ານລຸ່ມນັ້ນເຂົ້າບໍ່ໄດ້ພ້ອມກັນ', function () {
        $expired = Company::factory()->expired()->create();
        $store = Company::factory()->childOf($expired)->create(['type' => 'store']);

        expect($store->hasActiveAccess())->toBeFalse();
    });

    it('ປິດຮ້ານດຽວໄດ້ ໂດຍບໍ່ກະທົບບໍລິສັດແມ່', function () {
        $paid = Company::factory()->create(['plan' => 'active']);
        $store = Company::factory()->childOf($paid)->create(['type' => 'store', 'is_active' => false]);

        expect($store->hasActiveAccess())->toBeFalse()
            ->and($paid->hasActiveAccess())->toBeTrue();
    });
});

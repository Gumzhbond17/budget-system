<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);   // projects.budget_currency_code ມີ FK ໄປ currencies

    $this->admin = User::factory()->platformAdmin()->create();
    $this->company = Company::factory()->create(['code' => 'SSS', 'name' => 'ບໍລິສັດ ແສງສະຫວ່າງ']);
    $this->staff = User::factory()->create(['company_id' => $this->company->id]);
});

describe('ສິດເຂົ້າເຖິງ', function () {
    it('ຜູ້ໃຊ້ທຳມະດາເຫັນສະເພາະອົງກອນຂອງຕົນ', function () {
        Company::factory()->create(['name' => 'ອົງກອນລູກຄ້າອື່ນ']);

        Sanctum::actingAs($this->staff);

        $data = $this->getJson('/api/v1/companies')->assertOk()->json('data');

        expect($data)->toHaveCount(1)
            ->and($data[0]['code'])->toBe('SSS');
    });

    it('ຜູ້ດູແລລະບົບເຫັນທຸກອົງກອນ', function () {
        Company::factory()->count(3)->create();

        Sanctum::actingAs($this->admin);

        // 1 (SSS) + 3 = 4
        expect($this->getJson('/api/v1/companies')->assertOk()->json('data'))->toHaveCount(4);
    });

    /**
     * ແຖວອົງກອນລະດັບເທິງຄືບ່ອນທີ່ເກັບແພັກເກັດ ແລະ ວັນໝົດອາຍຸ — ຖ້າຜູ້ໃຊ້ແກ້ໄດ້
     * ກໍຕໍ່ອາຍຸ trial ໃຫ້ຕົນເອງໄດ້ນຳ. ຍັງເປັນວຽກຂອງຜູ້ດູແລລະບົບຢູ່.
     */
    it('ຜູ້ໃຊ້ທຳມະດາແກ້/ລຶບແຖວອົງກອນຂອງຕົນບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->staff);

        $this->patchJson("/api/v1/companies/{$this->company->uuid}", ['name' => 'ຊື່ໃໝ່'])->assertForbidden();
        $this->deleteJson("/api/v1/companies/{$this->company->uuid}")->assertForbidden();
    });

    it('ຜູ້ໃຊ້ທຳມະດາສ້າງອົງກອນລະດັບເທິງບໍ່ໄດ້ — ໄດ້ແຕ່ສາຂາ/ຮ້ານ', function () {
        Sanctum::actingAs($this->staff);

        foreach (['internal', 'third_party'] as $type) {
            $this->postJson('/api/v1/companies', ['name' => 'ອົງກອນໃໝ່', 'type' => $type])
                ->assertStatus(422)
                ->assertJsonValidationErrors('type');
        }
    });

    it('ອົງກອນອື່ນເບິ່ງລາຍລະອຽດກັນບໍ່ໄດ້', function () {
        $other = Company::factory()->create();

        Sanctum::actingAs($this->staff);

        $this->getJson("/api/v1/companies/{$other->uuid}")->assertForbidden();
    });
});

describe('CRUD', function () {
    beforeEach(fn () => Sanctum::actingAs($this->admin));

    it('ສ້າງອົງກອນໄດ້', function () {
        $this->postJson('/api/v1/companies', [
            'code' => 'PSG',
            'name' => 'ບໍລິສັດ ພູສີກຣຸບ',
            'type' => 'third_party',
        ])->assertCreated()->assertJsonPath('data.code', 'PSG');

        expect(Company::query()->where('code', 'PSG')->exists())->toBeTrue();
    });

    it('ລະຫັດອົງກອນຊ້ຳບໍ່ໄດ້', function () {
        $this->postJson('/api/v1/companies', ['code' => 'SSS', 'name' => 'ຊື່ອື່ນ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });

    it('ແກ້ໄຂໄດ້', function () {
        $this->patchJson("/api/v1/companies/{$this->company->uuid}", ['name' => 'ຊື່ໃໝ່'])
            ->assertOk()
            ->assertJsonPath('data.name', 'ຊື່ໃໝ່');
    });

    it('ລຶບອົງກອນທີ່ຍັງມີໂຄງການບໍ່ໄດ້', function () {
        Project::factory()->create(['company_id' => $this->company->id]);

        $this->deleteJson("/api/v1/companies/{$this->company->uuid}")->assertForbidden();
    });

    it('ລຶບອົງກອນທີ່ຍັງມີຜູ້ໃຊ້ບໍ່ໄດ້', function () {
        // $this->staff ຍັງສັງກັດຢູ່
        $this->deleteJson("/api/v1/companies/{$this->company->uuid}")->assertForbidden();
    });

    it('ລຶບອົງກອນເປົ່າໄດ້ ແລະ ເປັນ soft delete', function () {
        $empty = Company::factory()->create();

        $this->deleteJson("/api/v1/companies/{$empty->uuid}")->assertNoContent();

        expect(Company::query()->find($empty->id))->toBeNull()
            ->and(Company::withTrashed()->find($empty->id))->not->toBeNull();
    });
});

describe('ຕົ້ນໄມ້ສາຂາ', function () {
    beforeEach(fn () => Sanctum::actingAs($this->admin));

    it('ສ້າງສາຂາໃຕ້ອົງກອນແມ່ ແລ້ວ path/depth ຖືກຕັ້ງໃຫ້ເອງ', function () {
        $this->postJson('/api/v1/companies', [
            'code' => 'SSS-VTE',
            'name' => 'ສາຂາວຽງຈັນ',
            'type' => 'branch',
            'parent_uuid' => $this->company->uuid,
        ])->assertCreated();

        $branch = Company::query()->where('code', 'SSS-VTE')->sole();

        expect($branch->depth)->toBe(1)
            ->and($branch->path)->toBe("/{$this->company->id}/{$branch->id}/")
            ->and($branch->parent_id)->toBe($this->company->id);
    });

    it('ຍ້າຍກິ່ງແລ້ວ path ຂອງລູກຫຼານຖືກຂຽນຄືນ', function () {
        $branch = Company::factory()->childOf($this->company)->create();
        $sub = Company::factory()->childOf($branch)->create();
        $newRoot = Company::factory()->create();

        $this->patchJson("/api/v1/companies/{$branch->uuid}", ['parent_uuid' => $newRoot->uuid])
            ->assertOk();

        expect($branch->fresh()->path)->toBe("/{$newRoot->id}/{$branch->id}/")
            ->and($sub->fresh()->path)->toBe("/{$newRoot->id}/{$branch->id}/{$sub->id}/");
    });

    it('ເອົາລູກຫຼານມາເປັນອົງກອນແມ່ບໍ່ໄດ້ (ວົງຈອນ)', function () {
        $branch = Company::factory()->childOf($this->company)->create();

        $this->patchJson("/api/v1/companies/{$this->company->uuid}", ['parent_uuid' => $branch->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_uuid');
    });

    it('ເອົາຕົນເອງມາເປັນອົງກອນແມ່ບໍ່ໄດ້', function () {
        $this->patchJson("/api/v1/companies/{$this->company->uuid}", ['parent_uuid' => $this->company->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_uuid');
    });
});

describe('ອາຍຸການໃຊ້ງານ', function () {
    it('trial ທີ່ບໍ່ໄດ້ກຳນົດວັນສິ້ນສຸດ ໄດ້ 45 ວັນຈາກ config', function () {
        config(['company.trial_days' => 45]);

        $company = Company::query()->create([
            'code' => 'TRIAL-1',
            'name' => 'ອົງກອນທົດລອງ',
            'plan' => 'trial',
        ]);

        expect($company->trial_ends_at->toDateString())
            ->toBe(Carbon::today()->addDays(45)->toDateString())
            ->and($company->expiresInDays())->toBe(45)
            ->and($company->hasActiveAccess())->toBeTrue();
    });

    it('trial ທີ່ໝົດອາຍຸແລ້ວ ຖືວ່າເຂົ້າໃຊ້ບໍ່ໄດ້', function () {
        $company = Company::factory()->expired()->create();

        expect($company->hasActiveAccess())->toBeFalse()
            ->and($company->expiresInDays())->toBeLessThan(0);
    });

    it('plan = active ທີ່ບໍ່ມີວັນໝົດອາຍຸ ໃຊ້ໄດ້ຕະຫຼອດ', function () {
        $company = Company::factory()->create(['plan' => 'active', 'access_expires_at' => null]);

        expect($company->hasActiveAccess())->toBeTrue()
            ->and($company->expiresInDays())->toBeNull();
    });

    it('plan = suspended ເຂົ້າໃຊ້ບໍ່ໄດ້ ເຖິງແມ່ນວັນໝົດອາຍຸຍັງເຫຼືອ', function () {
        $company = Company::factory()->suspended()->create([
            'access_expires_at' => Carbon::today()->addYear(),
        ]);

        expect($company->hasActiveAccess())->toBeFalse();
    });

    it('ປ່ຽນ plan ຈາກ trial ເປັນ active ແລ້ວອ່ານ access_expires_at ແທນ', function () {
        $company = Company::factory()->expired()->create();

        // trial ໝົດແລ້ວ ແຕ່ຊື້ແພັກເກັດ → ຕ້ອງກັບມາໃຊ້ໄດ້ທັນທີ
        $company->update(['plan' => 'active', 'access_expires_at' => Carbon::today()->addYear()]);

        expect($company->fresh()->hasActiveAccess())->toBeTrue();
    });
});

<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->admin = User::factory()->platformAdmin()->create();
    $this->company = Company::factory()->create(['code' => 'SSS']);
    $this->project = Project::factory()->create([
        'company_id' => $this->company->id,
        'code' => 'SOLAR-3MW',
        'name' => 'ໂຄງການ Solar 3 MW',
    ]);
    $this->owner = memberOf($this->project, 'full_access');
});

describe('ລາຍການໂຄງການ', function () {
    it('ຜູ້ໃຊ້ເຫັນສະເພາະໂຄງການທີ່ຕົນເປັນສະມາຊິກ', function () {
        Project::factory()->create(['company_id' => $this->company->id, 'name' => 'ໂຄງການທີ່ບໍ່ໄດ້ຮ່ວມ']);

        Sanctum::actingAs($this->owner);

        $data = $this->getJson('/api/v1/projects')->assertOk()->json('data');

        expect($data)->toHaveCount(1)
            ->and($data[0]['code'])->toBe('SOLAR-3MW')
            ->and($data[0]['my_role'])->toBe('full_access');
    });

    it('ຜູ້ດູແລລະບົບເຫັນທຸກໂຄງການ', function () {
        Project::factory()->count(2)->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->admin);

        expect($this->getJson('/api/v1/projects')->assertOk()->json('data'))->toHaveCount(3);
    });

    it('ກັ່ນຕອງຕາມອົງກອນໄດ້ — ນີ້ຄືຂັ້ນ "ເລືອກອົງກອນ → ເລືອກໂຄງການ"', function () {
        $other = Company::factory()->create();
        Project::factory()->create(['company_id' => $other->id]);

        Sanctum::actingAs($this->admin);

        $data = $this->getJson("/api/v1/projects?company_uuid={$this->company->uuid}")
            ->assertOk()
            ->json('data');

        expect($data)->toHaveCount(1)
            ->and($data[0]['company']['code'])->toBe('SSS');
    });
});

describe('CRUD', function () {
    it('ຜູ້ດູແລລະບົບສ້າງໂຄງການໃຫ້ອົງກອນໄດ້', function () {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/projects', [
            'company_uuid' => $this->company->uuid,
            'code' => 'SOLAR-XSB',
            'name' => 'ໂຄງການ ໄຊສົມບູນ',
            'budget_currency_code' => 'USD',
            'budget_amount' => '1,493,145.00',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SOLAR-XSB')
            // ຈຳນວນເງິນຄືນເປັນ minor units ບໍ່ແມ່ນ float (ພາກ 2.1.ກ)
            ->assertJsonPath('data.budget.minor', 149314500)
            ->assertJsonPath('data.budget.decimal', '1493145.00');
    });

    it('ຜູ້ສ້າງກາຍເປັນສະມາຊິກ full_access ທັນທີ', function () {
        // ໂຄງການທີ່ບໍ່ມີສະມາຊິກ = ບໍ່ມີໃຜເຂົ້າໄດ້ເລີຍ ຍ້ອນ ExpensePolicy
        // ບັງຄັບການເປັນສະມາຊິກ ໂດຍບໍ່ຍົກເວັ້ນໃຫ້ຜູ້ດູແລລະບົບ
        Sanctum::actingAs($this->admin);

        $uuid = $this->postJson('/api/v1/projects', [
            'company_uuid' => $this->company->uuid,
            'code' => 'SOLAR-XSB',
            'name' => 'ໂຄງການ ໄຊສົມບູນ',
        ])->assertCreated()->json('data.uuid');

        $created = Project::query()->where('uuid', $uuid)->sole();

        expect($created->members()->where('user_id', $this->admin->id)->value('role'))
            ->toBe('full_access');

        // ແລະ ຜົນທີ່ຕ້ອງການຈິງ: ເປີດລາຍການລາຍຈ່າຍໄດ້ທັນທີ ບໍ່ຕິດ 403
        $this->getJson("/api/v1/projects/{$uuid}/expenses")->assertOk();
    });

    it('ຜູ້ໃຊ້ທຳມະດາສ້າງໂຄງການບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/projects', [
            'company_uuid' => $this->company->uuid,
            'code' => 'X',
            'name' => 'ໂຄງການໃໝ່',
        ])->assertForbidden();
    });

    it('full_access ແກ້ໂຄງການຂອງຕົນໄດ້', function () {
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/v1/projects/{$this->project->uuid}", ['name' => 'ຊື່ໃໝ່'])
            ->assertOk()
            ->assertJsonPath('data.name', 'ຊື່ໃໝ່');
    });

    it('read_only ແກ້ບໍ່ໄດ້', function () {
        $reader = memberOf($this->project, 'read_only');

        Sanctum::actingAs($reader);

        $this->patchJson("/api/v1/projects/{$this->project->uuid}", ['name' => 'ຊື່ໃໝ່'])
            ->assertForbidden();
    });

    it('ລະຫັດໂຄງການຊ້ຳໃນອົງກອນດຽວກັນບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/projects', [
            'company_uuid' => $this->company->uuid,
            'code' => 'SOLAR-3MW',
            'name' => 'ຊື່ອື່ນ',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    });

    it('ລະຫັດດຽວກັນຢູ່ຄົນລະອົງກອນໄດ້', function () {
        // ອົງກອນ ຂ ຕ້ອງໃຊ້ລະຫັດ "SOLAR-3MW" ໄດ້ ເຖິງແມ່ນອົງກອນ ກ ຈອງໄປແລ້ວ
        $other = Company::factory()->create();

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/projects', [
            'company_uuid' => $other->uuid,
            'code' => 'SOLAR-3MW',
            'name' => 'ໂຄງການຊື່ດຽວກັນ ຄົນລະອົງກອນ',
        ])->assertCreated();
    });

    it('ລຶບໂຄງການທີ່ມີລາຍຈ່າຍແລ້ວບໍ່ໄດ້ — ແມ່ນແຕ່ຜູ້ດູແລລະບົບ', function () {
        seedBaseRates();
        createExpense($this->project, $this->owner);

        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/projects/{$this->project->uuid}")->assertForbidden();
    });

    it('ລຶບໂຄງການເປົ່າໄດ້', function () {
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/projects/{$this->project->uuid}")->assertNoContent();

        expect(Project::query()->find($this->project->id))->toBeNull()
            ->and(Project::withTrashed()->find($this->project->id))->not->toBeNull();
    });
});

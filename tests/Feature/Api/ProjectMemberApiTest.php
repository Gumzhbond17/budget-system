<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->company = Company::factory()->create();
    $this->project = Project::factory()->create(['company_id' => $this->company->id]);

    $this->owner = memberOf($this->project, 'full_access');
    $this->editor = memberOf($this->project, 'modify');
    $this->reader = memberOf($this->project, 'read_only');
});

describe('ລາຍຊື່ສະມາຊິກ', function () {
    it('ສະມາຊິກເບິ່ງລາຍຊື່ໄດ້', function () {
        Sanctum::actingAs($this->reader);

        $roles = collect(
            $this->getJson("/api/v1/projects/{$this->project->uuid}/members")->assertOk()->json('data')
        )->pluck('role');

        expect($roles)->toHaveCount(3)
            ->and($roles)->toContain('full_access', 'modify', 'read_only');
    });

    it('ຄົນນອກໂຄງການເບິ່ງບໍ່ໄດ້', function () {
        $outsider = User::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/projects/{$this->project->uuid}/members")->assertForbidden();
    });
});

describe('ມອບສິດ', function () {
    it('full_access ເພີ່ມສະມາຊິກໄດ້', function () {
        $newbie = User::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/members", [
            'user_id' => $newbie->id,
            'role' => 'read_only',
        ])->assertCreated()->assertJsonPath('data.role', 'read_only');

        expect($newbie->fresh()->hasProjectRole($this->project->id, ['read_only']))->toBeTrue();
    });

    it('modify ເພີ່ມສະມາຊິກບໍ່ໄດ້', function () {
        $newbie = User::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/members", [
            'user_id' => $newbie->id,
            'role' => 'read_only',
        ])->assertForbidden();
    });

    it('ເພີ່ມຜູ້ໃຊ້ຈາກອົງກອນອື່ນບໍ່ໄດ້', function () {
        // ນີ້ຄືເສັ້ນແບ່ງລະຫວ່າງລູກຄ້າ — ຖ້າຜ່ານ ຄົນນອກຈະເຫັນລາຍຈ່າຍທັນທີ
        $stranger = User::factory()->create();

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/members", [
            'user_id' => $stranger->id,
            'role' => 'read_only',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    });

    it('ເພີ່ມຄົນເກົ່າຊ້ຳບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/members", [
            'user_id' => $this->reader->id,
            'role' => 'modify',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    });

    it('role ນອກລາຍການບໍ່ຜ່ານ', function () {
        $newbie = User::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/projects/{$this->project->uuid}/members", [
            'user_id' => $newbie->id,
            'role' => 'superuser',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    });
});

describe('ປ່ຽນ / ຖອດສິດ', function () {
    it('ປ່ຽນລະດັບສິດໄດ້ ແລະ ມີຜົນທັນທີ', function () {
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/v1/projects/{$this->project->uuid}/members/{$this->reader->id}", [
            'role' => 'authorize',
        ])->assertOk()->assertJsonPath('data.role', 'authorize');

        expect($this->reader->fresh()->hasProjectRole($this->project->id, ['authorize']))->toBeTrue();
    });

    it('ຖອດສະມາຊິກອອກໄດ້', function () {
        Sanctum::actingAs($this->owner);

        $this->deleteJson("/api/v1/projects/{$this->project->uuid}/members/{$this->reader->id}")
            ->assertNoContent();

        expect($this->reader->fresh()->isMemberOf($this->project->id))->toBeFalse();
    });

    it('ຖອດ full_access ຄົນສຸດທ້າຍບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->owner);

        $this->deleteJson("/api/v1/projects/{$this->project->uuid}/members/{$this->owner->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        expect($this->owner->fresh()->isMemberOf($this->project->id))->toBeTrue();
    });

    it('ຫຼຸດສິດ full_access ຄົນສຸດທ້າຍບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/v1/projects/{$this->project->uuid}/members/{$this->owner->id}", [
            'role' => 'read_only',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    });

    it('ຖອດ full_access ໄດ້ ຖ້າຍັງມີອີກຄົນ', function () {
        $second = memberOf($this->project, 'full_access');

        Sanctum::actingAs($this->owner);

        $this->deleteJson("/api/v1/projects/{$this->project->uuid}/members/{$second->id}")
            ->assertNoContent();
    });

    it('ຄົນນອກໂຄງການ ຖືກມອງເປັນ 404 ບໍ່ແມ່ນ 403', function () {
        // ບໍ່ຮົ່ວແມ່ນແຕ່ຂໍ້ມູນວ່າ user id ນັ້ນມີຢູ່ຈິງ (docs/architecture.md ພາກ 9)
        $stranger = User::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/v1/projects/{$this->project->uuid}/members/{$stranger->id}", [
            'role' => 'read_only',
        ])->assertNotFound();
    });
});

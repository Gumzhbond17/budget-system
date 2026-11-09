<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

// ໂຄງການມີ FK ໄປ currencies (budget_currency_code)
beforeEach(fn () => $this->seed(CurrencySeeder::class));

describe('GET /api/v1/profile', function () {
    it('ຄືນຂໍ້ມູນຕົນເອງ ພ້ອມສິດຕໍ່ໂຄງການ', function () {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $project = Project::factory()->create(['company_id' => $company->id]);
        $project->members()->create(['user_id' => $user->id, 'role' => 'modify']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.company.uuid', $company->uuid)
            ->assertJsonCount(1, 'data.memberships')
            ->assertJsonPath('data.memberships.0.role', 'modify')
            ->assertJsonPath('data.memberships.0.project.uuid', $project->uuid);
    });

    it('ບໍ່ຮົ່ວສິດຂອງຜູ້ໃຊ້ຄົນອື່ນ', function () {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $other = User::factory()->create(['company_id' => $company->id]);
        Project::factory()->create(['company_id' => $company->id])
            ->members()->create(['user_id' => $other->id, 'role' => 'full_access']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonCount(0, 'data.memberships');
    });

    it('ຍັງເປີດໄດ້ເມື່ອອົງກອນໝົດອາຍຸ — ຈະໄດ້ເຫັນວ່າໝົດມື້ໃດ', function () {
        $company = Company::factory()->expired()->create();
        Sanctum::actingAs(User::factory()->create(['company_id' => $company->id]));

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.access.can_access', false);
    });

    it('ຕ້ອງ login', function () {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
    });
});

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $capacityKw = fake()->numberBetween(1, 10) * 1000;

        return [
            'uuid' => (string) Str::uuid7(),
            // ທຸກໂຄງການຕ້ອງສັງກັດອົງກອນ (NOT NULL ຢູ່ DB) — factory ສ້າງໃຫ້ເອງ
            // ເພື່ອໃຫ້ `Project::factory()->create()` ຍັງໃຊ້ໄດ້ຄືເກົ່າ
            'company_id' => Company::factory(),
            'code' => 'SOLAR-'.strtoupper(fake()->unique()->bothify('??##')),
            'name' => 'ໂຄງການ Solar '.($capacityKw / 1000).' MW',
            'location' => 'ນາຊາຍທອງ',
            'capacity_kw' => $capacityKw,
            'budget_currency_code' => 'USD',   // ຕ້ອງມີ CurrencySeeder ແລ່ນກ່ອນ (FK)
            'budget_amount_minor' => 0,
            'status' => 'active',
        ];
    }
}

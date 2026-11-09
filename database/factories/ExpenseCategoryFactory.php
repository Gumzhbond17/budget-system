<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // ໝວດຈິງທີ່ພົບໃນ sheet `Payment new` — docs/architecture.md ພາກ 1.3
        $categories = [
            'EQUIPMENT' => ['ອຸປະກອນຕ່າງໆ', 'Equipment'],
            'LABOR' => ['ຄ່າແຮງ', 'Labor'],
            'FUEL' => ['ນ້ຳມັນ', 'Fuel'],
            'RENT' => ['ຄ່າເຊົ່າ', 'Rent'],
            'FOOD' => ['ອາຫານ', 'Food'],
            'EDL' => ['EDL', 'Electricity'],
        ];

        $code = fake()->unique()->randomElement(array_keys($categories));

        return [
            'code' => $code,
            'name_lo' => $categories[$code][0],
            'name_en' => $categories[$code][1],
            'color' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function child(ExpenseCategory $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}

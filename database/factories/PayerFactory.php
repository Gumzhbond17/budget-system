<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payer>
 */
class PayerFactory extends Factory
{
    protected $model = Payer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // ຜູ້ຈ່າຍຈິງໃນໄຟລ໌ເດີມ — docs/architecture.md ພາກ 1.3
        return [
            'name' => fake()->unique()->randomElement(['Sinexay', 'SSS', 'Sengsamai', 'phanh']),
            'type' => 'company',
            'is_active' => true,
        ];
    }
}

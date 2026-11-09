<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->company().' Account',
            'url_link' => fake()->url(),
            'username' => fake()->userName(),

            // ຂຽນເປັນຄ່າດິບ — cast `encrypted` ເຂົ້າລະຫັດໃຫ້ຕອນບັນທຶກ
            'password' => 'S3cret-'.fake()->bothify('??##??'),

            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

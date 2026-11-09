<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // ຜູ້ໃຊ້ຕ້ອງມີອົງກອນທີ່ຍັງບໍ່ໝົດອາຍຸ ບໍ່ດັ່ງນັ້ນ middleware `access.active`
            // ຈະຕອບ 403 ທຸກ request. CompanyFactory ຕັ້ງ plan = active ໄວ້ໃຫ້ແລ້ວ.
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_platform_admin' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** ຜູ້ດູແລລະບົບ — ບໍ່ສັງກັດອົງກອນໃດ ແລະ ບໍ່ຖືກຈຳກັດດ້ວຍອາຍຸ */
    public function platformAdmin(): static
    {
        return $this->state(fn () => [
            'company_id' => null,
            'is_platform_admin' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

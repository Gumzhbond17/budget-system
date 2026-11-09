<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payee>
 */
class PayeeFactory extends Factory
{
    protected $model = Payee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['ຮ້ານຫລຽນໄຊ', 'ນາງ ບຸດສະບາ', 'EDL', 'ຮ້ານໄຊສະຫວ່າງ']),
            'type' => fake()->randomElement(['company', 'individual', 'government']),
            'is_active' => true,
        ];
    }

    public function withBankAccount(string $accountNo = '0101234567890'): static
    {
        return $this->state(fn (): array => [
            'bank_name' => 'BCEL',
            'bank_account_no' => $accountNo,
        ]);
    }
}

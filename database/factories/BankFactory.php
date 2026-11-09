<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // ລະຫັດຕ້ອງບໍ່ຊ້ຳ (unique constraint) ແລະ ຕ້ອງເປັນຕົວພິມໃຫຍ່ຄືກັບ
        // ທີ່ controller ບັງຄັບ — ບໍ່ດັ່ງນັ້ນ test ຈະຜ່ານໃນສະພາບທີ່ API ບໍ່ຍອມ
        return [
            'code' => strtoupper(fake()->unique()->bothify('BK##?')),
            'name_lo' => 'ທະນາຄານ '.fake()->company(),
            'name_en' => fake()->company().' Bank',
            'short_name' => null,
            'swift_code' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Revenue>
 *
 * ⚠️ ໃຊ້ສ້າງ fixture ໂຄງສ້າງເທົ່ານັ້ນ. ລາຍຮັບຈິງຕ້ອງສ້າງຜ່ານ `RevenueService`
 * ເພື່ອໃຫ້ຍອດແປງຄິດຢູ່ບ່ອນດຽວ — ເບິ່ງ helper `createRevenue()` ໃນ tests/Pest.php
 */
class RevenueFactory extends Factory
{
    protected $model = Revenue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'project_id' => Project::factory(),
            'payer_id' => null,
            'received_date' => '2026-08-21',
            'title' => 'ເງິນໂອນເຂົ້າໂຄງການ',
            'currency_code' => 'USD',
            'amount_minor' => 100_000,          // USD 1,000.00
            'fx_rate_per_usd' => '1.0000000000',
            'fx_rate_lak_per_usd' => '21760.0000000000',
            'amount_usd_minor' => 100_000,
            'amount_lak_minor' => 21_760_000,
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CO-??##')),
            'name' => 'ບໍລິສັດ '.fake()->company(),
            'name_en' => fake()->company(),
            'type' => 'internal',

            // ຄ່າເລີ່ມຕົ້ນຂອງ factory = ໃຊ້ໄດ້ຕະຫຼອດ ເພື່ອບໍ່ໃຫ້ test ອື່ນ
            // ລົ້ມແບບງຽບໆເມື່ອ trial ໝົດອາຍຸ. ໃຊ້ state ລຸ່ມນີ້ເມື່ອຢາກທົດສອບອາຍຸ.
            'plan' => 'active',
            'access_expires_at' => null,
            'is_active' => true,
        ];
    }

    /** ອົງກອນພາຍນອກທີ່ກຳລັງທົດລອງໃຊ້ — ຍັງເຫຼືອອາຍຸປົກກະຕິ */
    public function trial(?int $daysLeft = null): static
    {
        return $this->state(fn () => [
            'type' => 'third_party',
            'plan' => 'trial',
            'trial_ends_at' => Carbon::today()
                ->addDays($daysLeft ?? (int) config('company.trial_days', 45)),
        ]);
    }

    /** trial ທີ່ໝົດອາຍຸໄປແລ້ວ — ຜູ້ໃຊ້ຂອງອົງກອນນີ້ຕ້ອງເຂົ້າລະບົບບໍ່ໄດ້ */
    public function expired(): static
    {
        return $this->state(fn () => [
            'type' => 'third_party',
            'plan' => 'trial',
            'trial_ends_at' => Carbon::yesterday(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['plan' => 'suspended']);
    }

    /** ສາຂາຂອງອົງກອນອື່ນ */
    public function childOf(Company $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
            'type' => 'branch',
        ]);
    }
}

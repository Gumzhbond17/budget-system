<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(CurrencySeeder::class));

/**
 * ອາຍຸການໃຊ້ງານຢູ່ລະດັບ **ອົງກອນ** — ໝົດອາຍຸເທື່ອດຽວ ພະນັກງານທັງໝົດເຂົ້າບໍ່ໄດ້,
 * ຕໍ່ອາຍຸບ່ອນດຽວປົດລັອກໃຫ້ທຸກຄົນ.
 *
 * ນອກນັ້ນແຕ່ລະບັນຊີຍັງມີວັນໝົດອາຍຸຂອງຕົນໄດ້ ເຊິ່ງ **ຫຍໍ້** ອາຍຸຂອງອົງກອນ
 * ໃຫ້ສັ້ນລົງເທົ່ານັ້ນ ຢືດໃຫ້ຍາວກວ່າບໍ່ໄດ້ — ເບິ່ງ describe ສຸດທ້າຍຂອງໄຟລ໌ນີ້.
 */
describe('ອົງກອນໝົດອາຍຸ', function () {
    it('trial ໝົດອາຍຸ → 403 ພ້ອມ code ທີ່ SPA ແຍກອອກໄດ້', function () {
        $company = Company::factory()->expired()->create(['name' => 'ອົງກອນທົດລອງ']);
        $user = User::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/companies')
            ->assertForbidden()
            ->assertJsonPath('code', 'access_expired');
    });

    it('ບລັອກທຸກ endpoint ຂອງລະບົບ ບໍ່ແມ່ນສະເພາະໜ້າຕັ້ງຄ່າ', function () {
        $company = Company::factory()->expired()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);
        $user = memberOf($project, 'full_access');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertForbidden();
        $this->getJson("/api/v1/projects/{$project->uuid}/expenses")->assertForbidden();
    });

    it('ຍັງດຶງ /user ໄດ້ ເພື່ອໃຫ້ SPA ສະແດງໜ້າ "ໝົດອາຍຸ" ພ້ອມຊື່ຜູ້ໃຊ້', function () {
        $company = Company::factory()->expired()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'name' => 'ທ້າວ ພັນ']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.name', 'ທ້າວ ພັນ')
            ->assertJsonPath('data.access.can_access', false);
    });

    it('ຕໍ່ອາຍຸແລ້ວກັບມາໃຊ້ໄດ້ທັນທີ', function () {
        $company = Company::factory()->expired()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/currencies')->assertForbidden();

        $company->update(['plan' => 'active', 'access_expires_at' => Carbon::today()->addYear()]);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/currencies')->assertOk();
    });

    it('ວັນສຸດທ້າຍຂອງ trial ຍັງໃຊ້ໄດ້ (ບໍ່ໄດ້ຕັດຖິ້ມກ່ອນເວລາ)', function () {
        $company = Company::factory()->create([
            'plan' => 'trial',
            'trial_ends_at' => Carbon::today(),
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertOk();
    });
});

describe('ບັນຊີຖືກປິດ', function () {
    it('is_active = false → ເຂົ້າບໍ່ໄດ້ ເຖິງແມ່ນອົງກອນຍັງດີຢູ່', function () {
        $user = User::factory()->inactive()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertForbidden();
    });

    it('ຜູ້ໃຊ້ທີ່ຍັງບໍ່ໄດ້ຈັດສັນເຂົ້າອົງກອນ ເຂົ້າບໍ່ໄດ້', function () {
        $user = User::factory()->create(['company_id' => null]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertForbidden();
    });
});

describe('ອາຍຸລາຍບັນຊີ', function () {
    it('ບັນຊີໝົດອາຍຸ → ເຂົ້າບໍ່ໄດ້ ເຖິງແມ່ນອົງກອນຍັງດີຢູ່', function () {
        $user = User::factory()->create(['access_expires_at' => Carbon::yesterday()]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertForbidden();
    });

    it('ວັນສຸດທ້າຍຍັງໃຊ້ໄດ້ (ບໍ່ໄດ້ຕັດຖິ້ມກ່ອນເວລາ)', function () {
        $user = User::factory()->create(['access_expires_at' => Carbon::today()]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertOk();
    });

    it('ບໍ່ໄດ້ຕັ້ງວັນ = ໃຊ້ຕາມອົງກອນຢ່າງດຽວ', function () {
        $user = User::factory()->create(['access_expires_at' => null]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertOk();
    });

    /**
     * ຫົວໃຈຂອງຂໍ້ຕົກລົງ: ວັນຂອງບັນຊີ **ຫຍໍ້ໄດ້ຢ່າງດຽວ**.
     *
     * ຖ້າມັນຢືດອາຍຸໄດ້ ການລະງັບອົງກອນທີ່ບໍ່ຈ່າຍເງິນຈະຮົ່ວ — ຜູ້ໃຊ້ຄົນໃດທີ່ເຄີຍ
     * ຖືກຕັ້ງວັນໄກໆໄວ້ ຈະຍັງເຂົ້າໄດ້ຕໍ່ໄປໂດຍບໍ່ມີໃຜສັງເກດ.
     */
    it('ຕັ້ງວັນຊ້າກວ່າອົງກອນ ບໍ່ໄດ້ຢືດອາຍຸໃຫ້ດົນກວ່າອົງກອນ', function () {
        $company = Company::factory()->expired()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'access_expires_at' => Carbon::today()->addYear(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/currencies')->assertForbidden();
    });

    it('ບອກເຫດຜົນວ່າເປັນບັນຊີ ບໍ່ແມ່ນອົງກອນ', function () {
        $company = Company::factory()->create(['name' => 'ອົງກອນທີ່ຍັງດີ']);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'access_expires_at' => Carbon::yesterday(),
        ]);

        Sanctum::actingAs($user);

        $message = $this->getJson('/api/v1/currencies')->assertForbidden()->json('message');

        expect($message)->toContain('ບັນຊີ')
            ->and($message)->not->toContain('ອົງກອນທີ່ຍັງດີ');
    });
});

describe('ຜູ້ດູແລລະບົບ', function () {
    it('ບໍ່ຖືກຈຳກັດດ້ວຍອາຍຸ — ບໍ່ດັ່ງນັ້ນຈະລັອກຕົນເອງອອກ', function () {
        $admin = User::factory()->platformAdmin()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/currencies')->assertOk();
        $this->getJson('/api/v1/companies')->assertOk();
    });

    it('ວັນໝົດອາຍຸລາຍບັນຊີກໍບໍ່ລັອກຜູ້ດູແລລະບົບ', function () {
        $admin = User::factory()->platformAdmin()->create([
            'access_expires_at' => Carbon::yesterday(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/currencies')->assertOk();
    });

    it('ຍັງຖືກປິດດ້ວຍ is_active = false ໄດ້ຢູ່', function () {
        $admin = User::factory()->platformAdmin()->inactive()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/currencies')->assertForbidden();
    });
});

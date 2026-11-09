<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);

    $this->admin = User::factory()->platformAdmin()->create();
    $this->company = Company::factory()->create(['code' => 'SSS']);
    $this->staff = User::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'ທ້າວ ພັນ',
    ]);
});

describe('ສິດເຂົ້າເຖິງ', function () {
    it('ຜູ້ໃຊ້ທຳມະດາເຫັນສະເພາະເພື່ອນຮ່ວມອົງກອນ', function () {
        User::factory()->create(['company_id' => $this->company->id, 'name' => 'ນາງ ດາວ']);
        User::factory()->create(['name' => 'ຄົນຂອງລູກຄ້າອື່ນ']);   // ອົງກອນອື່ນ

        Sanctum::actingAs($this->staff);

        $names = collect($this->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('name');

        expect($names)->toHaveCount(2)
            ->and($names)->toContain('ທ້າວ ພັນ', 'ນາງ ດາວ')
            ->and($names)->not->toContain('ຄົນຂອງລູກຄ້າອື່ນ');
    });

    it('ຜູ້ໃຊ້ທຳມະດາສ້າງ/ແກ້ຜູ້ໃຊ້ບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->staff);

        $this->postJson('/api/v1/users', [
            'name' => 'ຄົນໃໝ່',
            'email' => 'new@example.com',
            'password' => 'correct-horse-battery',
        ])->assertForbidden();

        $this->patchJson("/api/v1/users/{$this->staff->id}", ['name' => 'ຊື່ໃໝ່'])->assertForbidden();
    });

    it('ບໍ່ຮົ່ວ password hash ອອກທາງ API', function () {
        Sanctum::actingAs($this->admin);

        $payload = $this->getJson("/api/v1/users/{$this->staff->id}")->assertOk()->json('data');

        expect($payload)->not->toHaveKey('password')
            ->and($payload)->not->toHaveKey('remember_token');
    });
});

describe('CRUD', function () {
    beforeEach(fn () => Sanctum::actingAs($this->admin));

    it('ສ້າງຜູ້ໃຊ້ພ້ອມຜູກເຂົ້າອົງກອນໄດ້', function () {
        $this->postJson('/api/v1/users', [
            'name' => 'ນາງ ບຸດສະບາ',
            'email' => 'bouasaba@example.com',
            'password' => 'correct-horse-battery',
            'company_uuid' => $this->company->uuid,
        ])->assertCreated()->assertJsonPath('data.company.code', 'SSS');

        $user = User::query()->where('email', 'bouasaba@example.com')->sole();

        expect($user->company_id)->toBe($this->company->id)
            ->and($user->is_active)->toBeTrue()
            ->and($user->is_platform_admin)->toBeFalse();
    });

    it('ລະຫັດຜ່ານຖືກ hash ບໍ່ໄດ້ເກັບເປັນ plain text', function () {
        $this->postJson('/api/v1/users', [
            'name' => 'ຄົນໃໝ່',
            'email' => 'plain@example.com',
            'password' => 'correct-horse-battery',
            'company_uuid' => $this->company->uuid,
        ])->assertCreated();

        expect(User::query()->where('email', 'plain@example.com')->value('password'))
            ->not->toBe('correct-horse-battery');
    });

    it('ອີເມວຊ້ຳບໍ່ໄດ້', function () {
        $this->postJson('/api/v1/users', [
            'name' => 'ຊ້ຳ',
            'email' => $this->staff->email,
            'password' => 'correct-horse-battery',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    });

    it('ແກ້ໄຂໂດຍບໍ່ສົ່ງລະຫັດຜ່ານ = ບໍ່ປ່ຽນລະຫັດຜ່ານ', function () {
        $before = $this->staff->password;

        $this->patchJson("/api/v1/users/{$this->staff->id}", ['name' => 'ຊື່ໃໝ່'])
            ->assertOk()
            ->assertJsonPath('data.name', 'ຊື່ໃໝ່');

        expect($this->staff->fresh()->password)->toBe($before);
    });

    it('ປິດບັນຊີດ້ວຍ is_active = false ໄດ້', function () {
        $this->patchJson("/api/v1/users/{$this->staff->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($this->staff->fresh()->canAccessSystem())->toBeFalse();
    });

    it('ຕັ້ງວັນໝົດອາຍຸໃຫ້ບັນຊີໄດ້', function () {
        $date = Carbon::today()->addDays(30)->toDateString();

        $this->patchJson("/api/v1/users/{$this->staff->id}", ['access_expires_at' => $date])
            ->assertOk()
            ->assertJsonPath('data.access.user_expires_at', $date);

        expect($this->staff->fresh()->access_expires_at->toDateString())->toBe($date);
    });

    it('ສົ່ງ null = ຖອດຂໍ້ຈຳກັດລາຍຄົນອອກ ກັບໄປໃຊ້ຕາມອົງກອນ', function () {
        $this->staff->update(['access_expires_at' => Carbon::yesterday()]);

        expect($this->staff->fresh()->canAccessSystem())->toBeFalse();

        $this->patchJson("/api/v1/users/{$this->staff->id}", ['access_expires_at' => null])
            ->assertOk()
            ->assertJsonPath('data.access.user_expires_at', null)
            ->assertJsonPath('data.access.can_access', true);

        expect($this->staff->fresh()->access_expires_at)->toBeNull();
    });

    it('ວັນທີບໍ່ຖືກຮູບແບບ = 422', function () {
        $this->patchJson("/api/v1/users/{$this->staff->id}", ['access_expires_at' => 'ມື້ອື່ນ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_expires_at');
    });

    it('ລຶບຕົນເອງບໍ່ໄດ້', function () {
        $this->deleteJson("/api/v1/users/{$this->admin->id}")->assertForbidden();
    });

    it('ລຶບຜູ້ໃຊ້ທີ່ຍັງຢູ່ໃນໂຄງການບໍ່ໄດ້', function () {
        $project = Project::factory()->create(['company_id' => $this->company->id]);
        $member = memberOf($project, 'modify');

        $this->deleteJson("/api/v1/users/{$member->id}")->assertForbidden();
    });
});

describe('ຂໍ້ມູນອາຍຸໃນ payload', function () {
    beforeEach(fn () => Sanctum::actingAs($this->admin));

    it('ສະຫຼຸບອາຍຸຈາກອົງກອນມາໃຫ້ຕາຕະລາງຜູ້ໃຊ້', function () {
        $trialCompany = Company::factory()->trial(daysLeft: 12)->create();
        $user = User::factory()->create(['company_id' => $trialCompany->id]);

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.access.plan', 'trial')
            ->assertJsonPath('data.access.expires_in_days', 12)
            ->assertJsonPath('data.access.can_access', true);
    });

    it('ລາຍງານວັນທີທີ່ມາຮອດກ່ອນ ເມື່ອບັນຊີຫຍໍ້ອາຍຸໃຫ້ສັ້ນກວ່າອົງກອນ', function () {
        $trialCompany = Company::factory()->trial(daysLeft: 12)->create();
        $user = User::factory()->create([
            'company_id' => $trialCompany->id,
            'access_expires_at' => Carbon::today()->addDays(5),
        ]);

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.access.expires_in_days', 5)
            ->assertJsonPath(
                'data.access.expires_at',
                Carbon::today()->addDays(5)->toDateString()
            )
            ->assertJsonPath(
                'data.access.company_expires_at',
                Carbon::today()->addDays(12)->toDateString()
            );
    });

    /** ວັນຂອງບັນຊີຢືດເກີນອົງກອນບໍ່ໄດ້ — payload ຕ້ອງສະທ້ອນຄວາມຈິງນັ້ນ */
    it('ວັນຂອງບັນຊີທີ່ຊ້າກວ່າອົງກອນ ບໍ່ຖືກລາຍງານເປັນວັນທີ່ມີຜົນ', function () {
        $trialCompany = Company::factory()->trial(daysLeft: 12)->create();
        $user = User::factory()->create([
            'company_id' => $trialCompany->id,
            'access_expires_at' => Carbon::today()->addDays(90),
        ]);

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.access.expires_in_days', 12)
            ->assertJsonPath(
                'data.access.user_expires_at',
                Carbon::today()->addDays(90)->toDateString()
            );
    });

    it('ຜູ້ດູແລລະບົບບໍ່ມີວັນໝົດອາຍຸ ເຖິງແມ່ນມີວັນຄ້າງຢູ່ໃນຖານຂໍ້ມູນ', function () {
        $admin = User::factory()->platformAdmin()->create([
            'access_expires_at' => Carbon::yesterday(),
        ]);

        $this->getJson("/api/v1/users/{$admin->id}")
            ->assertOk()
            ->assertJsonPath('data.access.expires_at', null)
            ->assertJsonPath('data.access.expires_in_days', null)
            ->assertJsonPath('data.access.can_access', true);
    });
});

<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Credential;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // projects.budget_currency_code ມີ FK ໄປຫາ currencies (ຄືກັບ BankApiTest)
    $this->seed(CurrencySeeder::class);

    $this->admin = User::factory()->platformAdmin()->create();

    // ຜູ້ໃຊ້ທີ່ມີສິດສູງສຸດໃນໂຄງການ ແຕ່ **ບໍ່ແມ່ນ** ຜູ້ດູແລລະບົບ — ນີ້ຄືເສັ້ນ
    // ແບ່ງທີ່ CredentialPolicy ວາງໄວ້ ແລະ ຕ່າງຈາກ MasterDataPolicy
    $this->owner = memberOf(Project::factory()->create(), 'full_access');
});

describe('ສິດເຂົ້າເຖິງ', function () {
    it('ຜູ້ໃຊ້ທຳມະດາອ່ານລາຍການບໍ່ໄດ້ ເຖິງແມ່ນມີສິດເຕັມໃນໂຄງການ', function () {
        Credential::factory()->create();

        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/credentials')->assertForbidden();
    });

    it('ຜູ້ໃຊ້ທຳມະດາສ້າງບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/credentials', [
            'title' => 'ບັນຊີໃໝ່',
            'password' => 'x',
        ])->assertForbidden();
    });

    it('ຜູ້ໃຊ້ທຳມະດາເປີດເບິ່ງລະຫັດບໍ່ໄດ້ ເຖິງແມ່ນພິມລະຫັດຜ່ານຕົນເອງຖືກ', function () {
        $credential = Credential::factory()->create();

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", [
            'password' => 'password',
        ])->assertForbidden();

        // ບໍ່ຖືວ່າເປັນການ "ພິມລະຫັດຜິດ" — policy ຕັດຕັ້ງແຕ່ກ່ອນຮອດການກວດ.
        // ກັ່ນສະເພາະເຫດການເປີດເບິ່ງ: factory ຂ້າງເທິງກໍຂຽນແຖວ "created" ລົງ log ຄືກັນ
        expect(ActivityLog::query()->whereIn('event', ['revealed', 'reveal_failed'])->count())->toBe(0);
    });
});

describe('CRUD', function () {
    it('ບໍ່ຄືນລະຫັດອອກໄປກັບລາຍການເລີຍ — ມີແຕ່ຄ່າແທນ', function () {
        Credential::factory()->create([
            'title' => 'LBB App Account',
            'username' => '28924594',
            'password' => 'G@17umzh93#$',
        ]);

        Sanctum::actingAs($this->admin);

        $row = $this->getJson('/api/v1/credentials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        expect($row)->not->toHaveKey('password')
            ->and($row['password_masked'])->toBe(Credential::MASK)
            ->and(json_encode($row))->not->toContain('G@17umzh93');
    });

    it('ເກັບລະຫັດເປັນ ciphertext ໃນ DB ບໍ່ແມ່ນຂໍ້ຄວາມທຳມະດາ', function () {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/credentials', [
            'title' => 'Host Atom Web Hosting',
            'url_link' => 'https://103.30.127.13:8443',
            'username' => 'sssesltd',
            'password' => '480U0dvgmG',
        ])->assertCreated();

        // ອ່ານຄ່າດິບຜ່ານ query builder — Eloquent cast ຈະຖອດລະຫັດໃຫ້ ເຮັດໃຫ້
        // ການທົດສອບນີ້ຜ່ານໄປໂດຍບໍ່ໄດ້ພິສູດຫຍັງ ຖ້າດຶງຜ່ານ Model
        $stored = DB::table('credentials')->value('password');

        expect($stored)->not->toBe('480U0dvgmG')
            ->and($stored)->not->toContain('480U0dvgmG')
            // ຄືນມາອ່ານຜ່ານ Model ແລ້ວຕ້ອງໄດ້ຄ່າເດີມຄືນ (ສອງທາງ ບໍ່ແມ່ນ hash)
            ->and(Credential::query()->first()->password)->toBe('480U0dvgmG');
    });

    it('ບັນທຶກຜູ້ສ້າງໃຫ້ເອງ', function () {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/credentials', [
            'title' => 'E2PAY',
            'password' => '363659',
        ])->assertCreated();

        expect(Credential::query()->first()->created_by)->toBe($this->admin->id);
    });

    it('ຄືນຄ່າເລີ່ມຕົ້ນມາພ້ອມແຖວໃໝ່ ບໍ່ແມ່ນ null', function () {
        Sanctum::actingAs($this->admin);

        // ບໍ່ໄດ້ດຶງແຖວຄືນຫຼັງ insert — ຖ້າຄ່າເລີ່ມຕົ້ນມີແຕ່ຢູ່ DB ຈະໄດ້ null
        // ກັບມາ ແລ້ວ badge ຢູ່ຕາຕະລາງຈະຂຶ້ນວ່າ "ຢຸດໃຊ້" ທັງທີ່ຫາກໍ່ສ້າງ
        $this->postJson('/api/v1/credentials', [
            'title' => 'FINA FINTECH APP',
            'password' => 'G@17umzh93#$()',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 0);
    });

    it('ຕ້ອງມີຫົວຂໍ້ ແລະ ລະຫັດຕອນສ້າງ', function () {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/credentials', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'password']);
    });

    it('ແກ້ໄຂໂດຍປ່ອຍຊ່ອງລະຫັດຫວ່າງ = ບໍ່ປ່ຽນລະຫັດ', function () {
        $credential = Credential::factory()->create([
            'title' => 'RaiDrive Mount',
            'password' => 'Nam2026Som',
        ]);

        Sanctum::actingAs($this->admin);

        // ຟອມບໍ່ເຄີຍໄດ້ຮັບລະຫັດເກົ່າ ຈຶ່ງສົ່ງຄືນມາບໍ່ໄດ້ — ຖ້າຂຽນທັບຕາມນັ້ນ
        // ການແກ້ຊື່ເສີຍໆຈະລຶບລະຫັດຖິ້ມ
        $this->patchJson("/api/v1/credentials/{$credential->uuid}", [
            'title' => 'RaiDrive Mount (Namsom)',
            'password' => '',
        ])->assertOk();

        $credential->refresh();

        expect($credential->title)->toBe('RaiDrive Mount (Namsom)')
            ->and($credential->password)->toBe('Nam2026Som');
    });

    it('ສົ່ງລະຫັດໃໝ່ມາ = ປ່ຽນລະຫັດ', function () {
        $credential = Credential::factory()->create(['password' => 'ເກົ່າ']);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/credentials/{$credential->uuid}", [
            'password' => 'ໃໝ່',
        ])->assertOk();

        expect($credential->refresh()->password)->toBe('ໃໝ່');
    });

    it('ລຶບແລ້ວ ciphertext ຫາຍໄປແທ້ — ບໍ່ແມ່ນ soft delete', function () {
        $credential = Credential::factory()->create();

        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/credentials/{$credential->uuid}")->assertNoContent();

        expect(DB::table('credentials')->count())->toBe(0);
    });

    it('ເຫັນແຖວທີ່ຢຸດໃຊ້ສະເພາະເມື່ອຂໍ include_inactive', function () {
        Credential::factory()->inactive()->create(['title' => 'ບັນຊີເກົ່າ']);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/credentials')->assertOk()->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/credentials?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('ຄົ້ນຫາຕາມຫົວຂໍ້ / ລິ້ງ / ຊື່ຜູ້ໃຊ້ ແຕ່ບໍ່ແມ່ນຕາມລະຫັດ', function () {
        Credential::factory()->create([
            'title' => 'Outlook Account',
            'username' => 'vault-owner@example.com',
            'password' => 'ZZUNIQUEZZ',
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/credentials?search=vault-owner')
            ->assertOk()->assertJsonCount(1, 'data');

        // ຖ້າຄົ້ນຫາໃນລະຫັດໄດ້ = ເດົາລະຫັດດ້ວຍການພິມທີ່ລະຕົວແລ້ວເບິ່ງວ່າ
        // ແຖວໃດຍັງເຫຼືອໄດ້ ໂດຍບໍ່ຕ້ອງກົດເປີດເບິ່ງເລີຍ
        $this->getJson('/api/v1/credentials?search=ZZUNIQUEZZ')
            ->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('ການເປີດເບິ່ງລະຫັດ', function () {
    it('ຄືນລະຫັດຈິງເມື່ອພິມລະຫັດຜ່ານຂອງຕົນເອງຖືກ', function () {
        $credential = Credential::factory()->create(['password' => 'G@17umzh93#$()']);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", [
            'password' => 'password',   // ຄ່າທີ່ UserFactory ຕັ້ງໃຫ້
        ])
            ->assertOk()
            ->assertJsonPath('data.password', 'G@17umzh93#$()');
    });

    it('ປະຕິເສດເມື່ອລະຫັດຜ່ານບໍ່ຖືກ ແລະ ບໍ່ຮົ່ວຄ່າໃດເລີຍ', function () {
        $credential = Credential::factory()->create(['password' => 'ຄວາມລັບ']);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", [
            'password' => 'ເດົາຜິດ',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        expect($response->content())->not->toContain('ຄວາມລັບ');
    });

    it('ຕ້ອງພິມລະຫັດຜ່ານ — ສົ່ງມາຫວ່າງບໍ່ໄດ້', function () {
        $credential = Credential::factory()->create();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    });

    it('ບັນທຶກທັງການເປີດສຳເລັດ ແລະ ການພິມຜິດ ລົງ activity_logs', function () {
        $credential = Credential::factory()->create();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", ['password' => 'ຜິດ']);
        $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", ['password' => 'password']);

        // ກັ່ນສະເພາະເຫດການເປີດເບິ່ງ — factory ຂຽນແຖວ "created" ລົງ log ກ່ອນແລ້ວ
        $reveals = ActivityLog::query()
            ->where('subject_type', $credential->getMorphClass())
            ->where('subject_id', $credential->id)
            ->whereIn('event', ['revealed', 'reveal_failed'])
            ->orderBy('id');

        expect($reveals->pluck('event')->all())->toBe(['reveal_failed', 'revealed'])
            ->and($reveals->first()->causer_id)->toBe($this->admin->id);
    });

    it('ລາຍການບອກວ່າຖືກເປີດເບິ່ງຄັ້ງສຸດທ້າຍເມື່ອໃດ', function () {
        $credential = Credential::factory()->create();

        Sanctum::actingAs($this->admin);

        // ຍັງບໍ່ມີໃຜເປີດ → null (ບໍ່ແມ່ນ key ທີ່ຫາຍໄປ)
        $before = $this->getJson('/api/v1/credentials')->assertOk()->json('data.0');

        expect($before)->toHaveKey('last_revealed_at')
            ->and($before['last_revealed_at'])->toBeNull();

        $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", ['password' => 'password'])
            ->assertOk();

        $after = $this->getJson('/api/v1/credentials')->assertOk()->json('data.0');

        expect($after['last_revealed_at'])->not->toBeNull();
    });

    it('ບໍ່ນັບການພິມຜິດເປັນ "ເປີດເບິ່ງແລ້ວ"', function () {
        $credential = Credential::factory()->create();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/credentials/{$credential->uuid}/reveal", ['password' => 'ຜິດ']);

        $row = $this->getJson('/api/v1/credentials')->assertOk()->json('data.0');

        expect($row['last_revealed_at'])->toBeNull();
    });
});

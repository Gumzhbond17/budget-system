<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Payee;
use App\Models\Payer;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    RateLimiter::clear('');

    $this->project = Project::factory()->create();
    $this->editor = memberOf($this->project, 'modify');
    $this->admin = User::factory()->create(['is_platform_admin' => true]);
});

/** ແຖວ log ລ່າສຸດຂອງເຫດການທີ່ລະບຸ */
function lastLog(string $event): ?ActivityLog
{
    return ActivityLog::query()->where('event', $event)->latest('id')->first();
}

describe('login / logout', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'phanh@example.com',
            'password' => Hash::make('correct-horse'),
        ]);
    });

    it('ບັນທຶກ login ພ້ອມ IP ແລະ ອັບເດດ last_login_at', function () {
        $this->postJson('/login', ['email' => 'phanh@example.com', 'password' => 'correct-horse'])
            ->assertOk();

        $log = lastLog('login');

        expect($log)->not->toBeNull()
            ->and($log->causer_id)->toBe($this->user->id)
            ->and($log->subject_label)->toBe('phanh@example.com')
            ->and($log->ip_address)->not->toBeNull()
            ->and($this->user->fresh()->last_login_at)->not->toBeNull()
            // ການຂຽນ last_login_at ຕ້ອງບໍ່ກາຍເປັນແຖວ "ແກ້ໄຂຜູ້ໃຊ້"
            ->and(lastLog('updated'))->toBeNull();
    });

    it('ບັນທຶກ login ລົ້ມເຫຼວ ໂດຍບໍ່ເກັບລະຫັດຜ່ານທີ່ພິມ', function () {
        $this->postJson('/login', ['email' => 'phanh@example.com', 'password' => 'wrong-horse'])
            ->assertUnprocessable();

        $log = lastLog('login_failed');

        expect($log)->not->toBeNull()
            ->and($log->causer_id)->toBeNull()
            ->and($log->new_values)->toBe(['email' => 'phanh@example.com'])
            ->and(json_encode($log->toArray()))->not->toContain('wrong-horse');
    });

    it('ບັນທຶກ login ລົ້ມເຫຼວດ້ວຍອີເມວທີ່ບໍ່ມີໃນລະບົບ', function () {
        $this->postJson('/login', ['email' => 'ghost@example.com', 'password' => 'x'])
            ->assertUnprocessable();

        expect(lastLog('login_failed')->subject_label)->toBe('ghost@example.com')
            ->and(lastLog('login_failed')->subject_id)->toBeNull();
    });

    it('ບັນທຶກ logout', function () {
        $this->withHeader('Origin', 'http://localhost');
        $this->postJson('/login', ['email' => 'phanh@example.com', 'password' => 'correct-horse']);
        $this->postJson('/logout')->assertOk();

        expect(lastLog('logout')?->causer_id)->toBe($this->user->id);
    });
});

describe('CRUD', function () {
    beforeEach(fn () => Sanctum::actingAs($this->editor));

    it('ບັນທຶກ ສ້າງ / ແກ້ (ພ້ອມຄ່າເກົ່າ-ໃໝ່) / ລຶບ', function () {
        $id = $this->postJson('/api/v1/payers', ['name' => 'ບໍລິສັດ ກ', 'type' => 'company'])
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/v1/payers/{$id}", ['name' => 'ບໍລິສັດ ຂ'])->assertOk();
        $this->deleteJson("/api/v1/payers/{$id}")->assertSuccessful();

        $logs = ActivityLog::query()
            ->where('subject_type', (new Payer)->getMorphClass())
            ->where('subject_id', $id)
            ->orderBy('id')
            ->get();

        expect($logs->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
            ->and($logs->pluck('causer_id')->unique()->all())->toBe([$this->editor->id])
            ->and($logs[1]->old_values)->toBe(['name' => 'ບໍລິສັດ ກ'])
            ->and($logs[1]->new_values)->toBe(['name' => 'ບໍລິສັດ ຂ'])
            // label ເປັນ snapshot — ລຶບແລ້ວຍັງອ່ານອອກວ່າແມ່ນອັນໃດ
            ->and($logs[2]->subject_label)->toBe('ບໍລິສັດ ຂ');
    });

    it('ບໍ່ເກັບຄ່າຂອງ field ທີ່ model ເຊື່ອງໄວ້', function () {
        $payee = Payee::factory()->create(['bank_account_no' => '0101-1234-5678']);
        $user = User::factory()->create(['password' => Hash::make('super-secret')]);

        $payeeLog = ActivityLog::query()->where('subject_type', $payee->getMorphClass())->first();
        $userLog = ActivityLog::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)
            ->first();

        expect($payeeLog->new_values['bank_account_no'])->toBe('[hidden]')
            ->and($userLog->new_values['password'])->toBe('[hidden]')
            ->and(json_encode(ActivityLog::all()->toArray()))
            ->not->toContain('0101-1234-5678')
            ->not->toContain('super-secret');
    });

    it('ການປ່ຽນ status ໄດ້ຊື່ເຫດການຂອງມັນເອງ', function () {
        seedBaseRates();
        $expense = createExpense($this->project, $this->editor);
        $approver = memberOf($this->project, 'full_access');

        $this->postJson("/api/v1/expenses/{$expense->uuid}/submit")->assertOk();

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/expenses/{$expense->uuid}/approve")->assertOk();

        $events = ActivityLog::query()
            ->where('subject_type', (new Expense)->getMorphClass())
            ->where('subject_id', $expense->id)
            ->orderBy('id')
            ->get();

        expect($events->pluck('event')->all())->toBe(['created', 'submitted', 'approved'])
            ->and($events->last()->causer_id)->toBe($approver->id)
            ->and($events->last()->subject_label)->toBe($expense->fresh()->doc_no);
    });
});

describe('ການຄົ້ນຫາ', function () {
    beforeEach(fn () => Sanctum::actingAs($this->editor));

    it('ບັນທຶກຄຳຄົ້ນ ແລະ ບໍ່ຊ້ຳເມື່ອ auto-refresh ຍິງຄຳເດີມ', function () {
        $this->getJson('/api/v1/payers?search=ບໍລິສັດ')->assertOk();
        $this->getJson('/api/v1/payers?search=ບໍລິສັດ')->assertOk();
        $this->getJson('/api/v1/payers?search=ກີບ')->assertOk();

        $logs = ActivityLog::query()->where('event', 'searched')->orderBy('id')->get();

        expect($logs->pluck('subject_label')->all())->toBe(['ບໍລິສັດ', 'ກີບ'])
            ->and($logs[0]->new_values)->toBe(['term' => 'ບໍລິສັດ', 'area' => 'payers'])
            ->and($logs[0]->causer_id)->toBe($this->editor->id);
    });

    it('ບໍ່ບັນທຶກເມື່ອບໍ່ມີຄຳຄົ້ນ', function () {
        $this->getJson('/api/v1/payers')->assertOk();
        $this->getJson('/api/v1/payers?search=%20%20')->assertOk();

        expect(lastLog('searched'))->toBeNull();
    });
});

describe('GET /api/v1/activity-logs', function () {
    it('ຜູ້ໃຊ້ທົ່ວໄປເປີດບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->editor);

        $this->getJson('/api/v1/activity-logs')->assertForbidden();
    });

    it('ຜູ້ດູແລລະບົບເຫັນ ແລະ ກັ່ນຕອງໄດ້', function () {
        Sanctum::actingAs($this->editor);
        $this->postJson('/api/v1/payers', ['name' => 'ຮ້ານ ໃໝ່'])->assertCreated();

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/activity-logs?subject=payer&event=created')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_type', 'payer')
            ->assertJsonPath('data.0.subject_label', 'ຮ້ານ ໃໝ່')
            ->assertJsonPath('data.0.causer.email', $this->editor->email)
            ->assertJsonPath('meta.truncated', false);

        $this->getJson("/api/v1/activity-logs?causer_id={$this->admin->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('ບໍ່ມີ route ແກ້ ຫຼື ລຶບ log', function () {
        Sanctum::actingAs($this->admin);
        $log = ActivityLog::query()->firstOrFail();

        $this->deleteJson("/api/v1/activity-logs/{$log->id}")->assertNotFound();
        $this->patchJson("/api/v1/activity-logs/{$log->id}", [])->assertNotFound();
    });
});

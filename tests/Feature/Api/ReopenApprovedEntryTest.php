<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Laravel\Sanctum\Sanctum;

/*
 * ຜູ້ດູແລລະບົບຍົກເລີກການອະນຸມັດ ແລ້ວສົ່ງກັບໃຫ້ຜູ້ບັນທຶກແກ້ (approved → rejected).
 * ໃຊ້ເມື່ອລາຍການທີ່ອະນຸມັດແລ້ວມີອັດຕາແລກປ່ຽນ ຫຼື ຍອດຜິດ.
 */

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->editor = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');

    $this->admin = memberOf($this->project, 'read_only');
    $this->admin->forceFill(['is_platform_admin' => true])->save();

    /** ລາຍການທີ່ຜ່ານການອະນຸມັດແລ້ວ */
    $this->approved = function (string $kind = 'expenses') {
        $entry = $kind === 'expenses'
            ? createExpense($this->project, $this->editor, ['fx_rate_per_usd' => '21760'])
            : createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);
        $this->postJson("/api/v1/{$kind}/{$entry->uuid}/submit")->assertOk();

        Sanctum::actingAs($this->approver);
        $this->postJson("/api/v1/{$kind}/{$entry->uuid}/approve")->assertOk();

        return $entry->fresh();
    };
});

it('ຜູ້ດູແລລະບົບສົ່ງກັບໄດ້ ແລ້ວຜູ້ບັນທຶກແກ້ອັດຕາ ແລະ ສົ່ງອະນຸມັດຄືນໄດ້', function () {
    $expense = ($this->approved)();

    Sanctum::actingAs($this->admin);
    $this->getJson("/api/v1/expenses/{$expense->uuid}")
        ->assertOk()
        ->assertJsonPath('data.can.reopen', true);

    $this->postJson("/api/v1/expenses/{$expense->uuid}/reopen", ['reason' => 'ອັດຕາແລກປ່ຽນຜິດ'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.is_locked', false)
        ->assertJsonPath('data.approved_at', null)
        ->assertJsonPath('data.rejection_reason', 'ອັດຕາແລກປ່ຽນຜິດ');

    // ຜູ້ບັນທຶກແກ້ອັດຕາ (ເປັນກີບ) ແລ້ວສົ່ງຄືນ
    Sanctum::actingAs($this->editor);
    $this->patchJson("/api/v1/expenses/{$expense->uuid}", ['fx_rate_lak' => '21800'])
        ->assertOk()
        ->assertJsonPath('data.fx_rate_per_usd', '21800.0000000000');

    $this->postJson("/api/v1/expenses/{$expense->uuid}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    // ຕ້ອງອະນຸມັດໃໝ່ຕາມຂັ້ນຕອນປົກກະຕິ
    Sanctum::actingAs($this->approver);
    $this->postJson("/api/v1/expenses/{$expense->uuid}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('ລາຍຮັບກໍສົ່ງກັບໄດ້', function () {
    $revenue = ($this->approved)('revenues');

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/revenues/{$revenue->uuid}/reopen", ['reason' => 'ຍອດຜິດ'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

it('ຕ້ອງມີເຫດຜົນ', function () {
    $expense = ($this->approved)();

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/expenses/{$expense->uuid}/reopen", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($expense->fresh()->status)->toBe('approved');
});

it('ຜູ້ອະນຸມັດ ແລະ ເຈົ້າຂອງໂຄງການ ທີ່ບໍ່ແມ່ນຜູ້ດູແລລະບົບ ສົ່ງກັບບໍ່ໄດ້', function () {
    $expense = ($this->approved)();

    foreach ([$this->approver, memberOf($this->project, 'full_access'), $this->editor] as $user) {
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/expenses/{$expense->uuid}/reopen", ['reason' => 'ລອງ'])
            ->assertForbidden();
    }
});

it('ຜູ້ດູແລລະບົບທີ່ບໍ່ແມ່ນສະມາຊິກໂຄງການ ສົ່ງກັບບໍ່ໄດ້ — ບໍ່ມີ bypass', function () {
    $expense = ($this->approved)();

    Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));
    $this->postJson("/api/v1/expenses/{$expense->uuid}/reopen", ['reason' => 'ລອງ'])
        ->assertForbidden();
});

it('ສົ່ງກັບໄດ້ສະເພາະລາຍການທີ່ອະນຸມັດແລ້ວ', function () {
    $draft = createExpense($this->project, $this->editor, ['fx_rate_per_usd' => '21760']);

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/expenses/{$draft->uuid}/reopen", ['reason' => 'ລອງ'])
        ->assertForbidden();
});

it('ບັນທຶກໃນປະຫວັດການໃຊ້ງານ', function () {
    $expense = ($this->approved)();

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/expenses/{$expense->uuid}/reopen", ['reason' => 'ອັດຕາແລກປ່ຽນຜິດ'])
        ->assertOk();

    $log = ActivityLog::query()
        ->where('subject_type', (new Expense)->getMorphClass())
        ->where('subject_id', $expense->id)
        // ການປ່ຽນ status ມີຊື່ເຫດການຂອງມັນເອງ (ActivityLogger::WORKFLOW_EVENTS)
        ->where('event', 'rejected')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($this->admin->id)
        ->and($log->old_values['status'] ?? null)->toBe('approved');
});

<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    $this->project = Project::factory()->create();
});

it('ຮູ້ວ່າຜູ້ໃຊ້ເປັນສະມາຊິກໂຄງການບໍ', function () {
    $member = memberOf($this->project, 'read_only');
    $outsider = User::factory()->create();

    expect($member->isMemberOf($this->project->id))->toBeTrue()
        ->and($outsider->isMemberOf($this->project->id))->toBeFalse();
});

it('ບໍ່ຖືວ່າເປັນສະມາຊິກຂອງໂຄງການອື່ນ', function () {
    $member = memberOf($this->project, 'full_access');
    $other = Project::factory()->create();

    expect($member->isMemberOf($other->id))->toBeFalse();
});

it('ກວດ role ໄດ້ຢ່າງຖືກຕ້ອງ', function () {
    $accountant = memberOf($this->project, 'modify');

    expect($accountant->hasProjectRole($this->project->id, ['full_access', 'modify']))->toBeTrue()
        ->and($accountant->hasProjectRole($this->project->id, ['full_access', 'authorize']))->toBeFalse()
        ->and($accountant->hasProjectRole($this->project->id, []))->toBeFalse();
});

it('ຄືນ false ສຳລັບ role ໃນໂຄງການທີ່ບໍ່ໄດ້ເປັນສະມາຊິກ', function () {
    $owner = memberOf($this->project, 'full_access');
    $other = Project::factory()->create();

    expect($owner->hasProjectRole($other->id, ['full_access']))->toBeFalse();
});

it('ດຶງ role ຄັ້ງດຽວເຖິງວ່າຈະຖາມຫຼາຍເທື່ອ — ກັນ N+1 ໃນ Policy', function () {
    $user = memberOf($this->project, 'full_access');

    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach (range(1, 20) as $ignored) {
        $user->isMemberOf($this->project->id);
        $user->hasProjectRole($this->project->id, ['full_access']);
    }

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('ຕ້ອງລ້າງ memo ຫຼັງເພີ່ມສະມາຊິກໃນ request ດຽວກັນ', function () {
    $user = User::factory()->create();

    expect($user->isMemberOf($this->project->id))->toBeFalse();

    ProjectMember::query()->create([
        'project_id' => $this->project->id,
        'user_id' => $user->id,
        'role' => 'full_access',
    ]);

    expect($user->isMemberOf($this->project->id))->toBeFalse()   // ຍັງເປັນຄ່າ memo ເກົ່າ
        ->and($user->forgetProjectRoles()->isMemberOf($this->project->id))->toBeTrue();
});

it('ດຶງລາຍການໂຄງການຜ່ານ relation ພ້ອມ role ໃນ pivot', function () {
    $user = memberOf($this->project, 'authorize');

    $project = $user->projects()->sole();

    expect($project->id)->toBe($this->project->id)
        ->and($project->getAttribute('pivot')->role)->toBe('authorize');
});

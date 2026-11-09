<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ມອບສິດຜູ້ໃຊ້ເຂົ້າໂຄງການ — ຂັ້ນສຸດທ້າຍຂອງຂັ້ນຕອນ
 * "ເລືອກອົງກອນ → ເລືອກໂຄງການ → ມອບສິດຜູ້ໃຊ້".
 *
 * ນີ້ຄືຕາຕະລາງດຽວທີ່ຕັດສິນວ່າໃຜເຫັນ/ແກ້ຂໍ້ມູນການເງິນຂອງໂຄງການໃດໄດ້ —
 * ທຸກ Policy ອື່ນອ່ານມາຈາກນີ້ຜ່ານ User::hasProjectRole().
 */
final class ProjectMemberController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $members = $project->members()
            ->with('user')
            ->join('users', 'users.id', '=', 'project_members.user_id')
            ->orderBy('users.name')
            ->select('project_members.*')
            ->get();

        return ProjectMemberResource::collection($members);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::in(ProjectMember::ROLES)],
        ]);

        // whereKey()->firstOrFail() ບໍ່ແມ່ນ findOrFail() — findOrFail ຮັບ array ໄດ້
        // ຈຶ່ງຄືນ User|Collection ເຮັດໃຫ້ຊະນິດຂ້າງລຸ່ມບໍ່ແນ່ນອນ
        $user = User::query()->whereKey($validated['user_id'])->firstOrFail();

        $this->assertUserBelongsToProjectCompany($user, $project);

        if ($project->members()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'ຜູ້ໃຊ້ຄົນນີ້ຢູ່ໃນໂຄງການນີ້ແລ້ວ — ໃຫ້ແກ້ລະດັບສິດແທນ',
            ]);
        }

        $member = $project->members()->create([
            'user_id' => $user->id,
            'role' => $validated['role'],
        ]);

        return ProjectMemberResource::make($member->load('user'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Project $project, User $user): ProjectMemberResource
    {
        $this->authorize('manageMembers', $project);

        $validated = $request->validate([
            'role' => ['required', Rule::in(ProjectMember::ROLES)],
        ]);

        $member = $project->members()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotLastFullAccess($project, $member, $validated['role']);

        $member->update($validated);

        return ProjectMemberResource::make($member->load('user'));
    }

    public function destroy(Project $project, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $member = $project->members()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotLastFullAccess($project, $member, null);

        $member->delete();

        return response()->json(status: 204);
    }

    /**
     * ຜູ້ໃຊ້ຕ້ອງສັງກັດອົງກອນດຽວກັບໂຄງການ.
     *
     * ນີ້ຄືເສັ້ນແບ່ງລະຫວ່າງລູກຄ້າ: ຖ້າບໍ່ກວດ ຜູ້ດູແລລະບົບກົດຜິດເທື່ອດຽວ
     * ພະນັກງານຂອງອົງກອນ ກ ຈະເຫັນລາຍຈ່າຍຂອງອົງກອນ ຂ ທັນທີ.
     * ຜູ້ດູແລລະບົບ (company_id = null) ຍົກເວັ້ນ — ລາວດູແລທຸກອົງກອນຢູ່ແລ້ວ.
     */
    private function assertUserBelongsToProjectCompany(User $user, Project $project): void
    {
        if ($user->is_platform_admin) {
            return;
        }

        if ($user->company_id !== $project->company_id) {
            throw ValidationException::withMessages([
                'user_id' => 'ຜູ້ໃຊ້ຄົນນີ້ບໍ່ໄດ້ສັງກັດອົງກອນເຈົ້າຂອງໂຄງການນີ້',
            ]);
        }
    }

    /**
     * ຫ້າມຖອດ/ຫຼຸດສິດຄົນ full_access ຄົນສຸດທ້າຍ — ບໍ່ດັ່ງນັ້ນໂຄງການຈະ
     * ບໍ່ມີໃຜຈັດການສະມາຊິກໄດ້ອີກເລີຍ ນອກຈາກຜູ້ດູແລລະບົບ.
     *
     * @param  string|null  $newRole  null = ກຳລັງຖອດອອກຈາກໂຄງການ
     */
    private function assertNotLastFullAccess(Project $project, ProjectMember $member, ?string $newRole): void
    {
        if ($member->role !== 'full_access' || $newRole === 'full_access') {
            return;
        }

        $remaining = $project->members()
            ->where('role', 'full_access')
            ->whereKeyNot($member->id)
            ->exists();

        if (! $remaining) {
            throw ValidationException::withMessages([
                'role' => 'ໂຄງການຕ້ອງມີຜູ້ມີສິດ "ເຂົ້າເຖິງເຕັມ" ຢ່າງໜ້ອຍ 1 ຄົນ',
            ]);
        }
    }
}

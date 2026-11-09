<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * ຜູ້ໃຊ້ — ຂັ້ນທີສາມຂອງຂັ້ນຕອນ "ເລືອກອົງກອນ → ເລືອກໂຄງການ → ມອບສິດຜູ້ໃຊ້".
 *
 * ບ່ອນນີ້ຈັດການ **ບັນຊີ** (ສ້າງ, ປິດ, ຍ້າຍອົງກອນ, ຫຍໍ້ອາຍຸລາຍຄົນ).
 * ການມອບ **ສິດຕໍ່ໂຄງການ** ຢູ່ທີ່ ProjectMemberController.
 */
final class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $users = User::query()
            // ຜູ້ໃຊ້ທຳມະດາເຫັນສະເພາະເພື່ອນຮ່ວມອົງກອນ — ບໍ່ຮົ່ວລາຍຊື່ຜູ້ໃຊ້ຂອງລູກຄ້າລາຍອື່ນ.
            // ຜູ້ໃຊ້ທີ່ບໍ່ມີອົງກອນ (company_id = null) ຈະບໍ່ເຫັນໃຜເລີຍ ນອກຈາກຕົນເອງ.
            ->unless($actor->is_platform_admin, function ($q) use ($actor) {
                $actor->company_id === null
                    ? $q->whereKey($actor->id)
                    : $q->where('company_id', $actor->company_id);
            })
            ->when($request->filled('company_uuid'), function ($q) use ($request) {
                $q->whereHas('company', fn ($c) => $c->where('uuid', $request->string('company_uuid')));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';

                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->unless($request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->with('company')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = User::query()->create($this->validated($request));

        return UserResource::make($user->load('company'))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return UserResource::make(
            $user->load(['company', 'projectMemberships.project'])
        );
    }

    public function update(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $data = $this->validated($request, $user);

        // role ຖືກ memoize ໄວ້ໃນ instance — ບໍ່ກ່ຽວກັບການແກ້ບັນຊີ ແຕ່ຖ້າ
        // is_active ປ່ຽນ ຄຳຕອບຂອງ canAccessSystem() ຕ້ອງເປັນຄ່າໃໝ່
        $user->update($data);

        return UserResource::make($user->fresh()->load('company'));
    }

    public function destroy(User $user, AvatarService $avatars): JsonResponse
    {
        $this->authorize('delete', $user);

        // ລຶບໄຟລ໌ຮູບນຳ — ບໍ່ດັ່ງນັ້ນມັນຈະຄ້າງຢູ່ disk ໂດຍບໍ່ມີໃຜອ້າງເຖິງຕະຫຼອດໄປ
        $avatars->remove($user);

        $user->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $isCreate = $user === null;
        $sometimes = $isCreate ? [] : ['sometimes'];

        $validated = $request->validate([
            'name' => [...$sometimes, 'required', 'string', 'max:255'],
            'email' => [...$sometimes, 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user)],

            // ຕອນສ້າງຕ້ອງມີລະຫັດຜ່ານ; ຕອນແກ້ໄຂໃສ່ກໍໄດ້ບໍ່ໃສ່ກໍໄດ້ (ບໍ່ໃສ່ = ບໍ່ປ່ຽນ)
            'password' => [$isCreate ? 'required' : 'nullable', 'string', Password::defaults()],

            'company_uuid' => ['nullable', 'string', Rule::exists('companies', 'uuid')],
            'is_platform_admin' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],

            // ວັນໝົດອາຍຸຕໍ່ບັນຊີ — ຫຍໍ້ອາຍຸຂອງອົງກອນໃຫ້ສັ້ນລົງໄດ້ຢ່າງດຽວ
            // (ເບິ່ງ User::canAccessSystem). ຮັບ null ເພື່ອລຶບຂໍ້ຈຳກັດອອກ.
            'access_expires_at' => ['nullable', 'date'],
        ], [
            'email.unique' => 'ມີຜູ້ໃຊ້ອີເມວນີ້ຢູ່ແລ້ວ',
            'company_uuid.exists' => 'ບໍ່ພົບອົງກອນທີ່ລະບຸ',
            'access_expires_at.date' => 'ວັນໝົດອາຍຸບໍ່ຖືກຮູບແບບ',
        ]);

        if (array_key_exists('company_uuid', $validated)) {
            $validated['company_id'] = $validated['company_uuid'] === null
                ? null
                : (int) Company::query()->where('uuid', $validated['company_uuid'])->value('id');

            unset($validated['company_uuid']);
        }

        // ບໍ່ໄດ້ປ້ອນລະຫັດຜ່ານໃໝ່ = ຮັກສາອັນເກົ່າໄວ້ (cast 'hashed' ຈະ hash ໃຫ້ເອງ)
        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        return $validated;
    }
}

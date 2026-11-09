<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ອົງກອນ — ຂັ້ນທຳອິດຂອງຂັ້ນຕອນ "ເລືອກອົງກອນ → ເລືອກໂຄງການ → ມອບສິດຜູ້ໃຊ້".
 *
 * ມີແຕ່ຜູ້ດູແລລະບົບທີ່ຂຽນໄດ້ (CompanyPolicy) — ຖ້າອົງກອນແກ້ຂໍ້ມູນຕົນເອງໄດ້
 * ກໍຈະຕໍ່ອາຍຸ trial ໃຫ້ຕົນເອງໄດ້ນຳ.
 */
final class CompanyController extends Controller
{
    /** ລະຫັດທີ່ສ້າງເອງສັ້ນກວ່ານີ້ ບໍ່ມີປະໂຫຍດເປັນຕົວອ້າງອີງ — ເບິ່ງ generateCode() */
    private const MIN_CODE_LENGTH = 3;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        $user = $request->user();

        $companies = Company::query()
            // ຜູ້ໃຊ້ທຳມະດາເຫັນອົງກອນຕົນ **ພ້ອມສາຂາ/ຮ້ານທັງໝົດຂ້າງລຸ່ມ** —
            // ບໍ່ດັ່ງນັ້ນຮ້ານທີ່ຕົນຫາກໍສ້າງຈະບໍ່ປາກົດໃນລາຍການ.
            // ຍັງບໍ່ຮົ່ວອອກນອກຕົ້ນໄມ້ຕົນ ເພາະ LIKE ຜູກກັບ path ຂອງຕົນເອງ.
            ->unless($user->is_platform_admin, function ($q) use ($user) {
                $own = $user->company;

                $own === null
                    ? $q->whereRaw('1 = 0')
                    : $q->where('path', 'like', $own->path.'%');
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';

                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('name_en', 'like', $term));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('plan'), fn ($q) => $q->where('plan', $request->string('plan')))
            ->unless($request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->withCount(['projects', 'users', 'children'])
            // ຮຽງຕາມ path = ລູກຢູ່ໃຕ້ພໍ່ແມ່ພໍດີ ໂດຍບໍ່ຕ້ອງປະກອບຕົ້ນໄມ້ຢູ່ຝັ່ງ PHP
            ->orderBy('path')
            ->paginate($request->integer('per_page', 50));

        return CompanyResource::collection($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $company = DB::transaction(function () use ($data, $request): Company {
            $company = Company::query()->create($data);

            // ຮ້ານຕ້ອງບັນທຶກເງິນໄດ້ທັນທີ — ນັ້ນຄືເຫດຜົນທັງໝົດຂອງປະເພດນີ້
            if ($company->type === 'store') {
                $this->openBooksFor($company, $request->user());
            }

            return $company;
        });

        return CompanyResource::make($company->loadCount(['projects', 'users', 'children']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * ເປີດ "ບັນຊີ" ໃຫ້ຮ້ານ — ສ້າງໂຄງການ 1:1 ແລ້ວມອບສິດເຕັມໃຫ້ຜູ້ສ້າງ.
     *
     * ເປັນຫຍັງຮ້ານຈຶ່ງຕ້ອງມີໂຄງການເຊື່ອງຢູ່ຂ້າງຫຼັງ: `expenses.project_id` ແລະ
     * `revenues.project_id` ເປັນ NOT NULL ແລະ ທຸກ Policy ຂອງເງິນຖາມຫາ
     * `project_members`. ການເພີ່ມມິຕິ "ຮ້ານ" ໃສ່ຕາຕະລາງເງິນໂດຍກົງຈະຕ້ອງ
     * migrate ສອງຕາຕະລາງຫຼັກ ພ້ອມທັງ resource, ລາຍງານ ແລະ policy ທັງໝົດ.
     * ວິທີນີ້ໄດ້ຜົນຢ່າງດຽວກັນໂດຍບໍ່ແຕະແກນການເງິນເລີຍ.
     *
     * ຜູ້ໃຊ້ບໍ່ເຫັນຄຳວ່າ "ໂຄງການ" — ມັນໃຊ້ຊື່ດຽວກັນກັບຮ້ານ ຈຶ່ງປາກົດເປັນຮ້ານ
     * ຢູ່ຕົວເລືອກໜ້າທຸລະກຳ.
     */
    private function openBooksFor(Company $store, User $creator): void
    {
        $project = Project::query()->create([
            'uuid' => (string) Str::uuid7(),
            'company_id' => $store->id,
            // unique ຕໍ່ອົງກອນ ແລະ ຮ້ານມີໂຄງການດຽວ — ຄ່າຄົງທີ່ຈຶ່ງພຽງພໍ
            'code' => 'MAIN',
            'name' => $store->name,
            'status' => 'active',
            'created_by' => $creator->id,
        ]);

        // ຂາດແຖວນີ້ = ຮ້ານທີ່ບໍ່ມີໃຜເຂົ້າໄດ້ເລີຍ (ExpensePolicy ບໍ່ມີທາງລັດ
        // ໃຫ້ຜູ້ດູແລລະບົບ) — ອັນດຽວກັນກັບ ProjectController::store
        $project->members()->create([
            'user_id' => $creator->id,
            'role' => 'full_access',
        ]);

        // role ຖືກ memoize ໄວ້ໃນ instance ຕັ້ງແຕ່ຕົ້ນ request — ຖ້າບໍ່ລ້າງ
        // ການກວດສິດຕໍ່ຈາກນີ້ (ລວມທັງ CompanyResource) ຈະຍັງເຫັນຄ່າເກົ່າ
        $creator->forgetProjectRoles();
    }

    public function show(Company $company): CompanyResource
    {
        $this->authorize('view', $company);

        return CompanyResource::make(
            $company->load('parent')->loadCount(['projects', 'users', 'children'])
        );
    }

    public function update(Request $request, Company $company): CompanyResource
    {
        $this->authorize('update', $company);

        $company->update($this->validated($request, $company));

        return CompanyResource::make($company->loadCount(['projects', 'users', 'children']));
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        DB::transaction(function () use ($company): void {
            // ໂຄງການທີ່ລະບົບສ້າງໃຫ້ຮ້ານຕ້ອງໄປພ້ອມກັນ — ປະໄວ້ຈະກາຍເປັນໂຄງການ
            // ກຳພ້າທີ່ຍັງໂຜ່ຢູ່ຕົວເລືອກໜ້າທຸລະກຳ ທັງທີ່ຮ້ານຖືກລຶບໄປແລ້ວ.
            // Policy ຮັບປະກັນແລ້ວວ່າບໍ່ມີເງິນເຄື່ອນໄຫວຢູ່ໃນນັ້ນ.
            //
            // ທັງສອງຕາຕະລາງໃຊ້ softDeletes ຈຶ່ງບໍ່ຕີກັບ FK `restrictOnDelete`
            // (ຂໍ້ຈຳກັດນັ້ນມີຜົນສະເພາະການລຶບແຖວຈິງ).
            if ($company->type === 'store') {
                $company->projects()->each(fn (Project $project) => $project->delete());
            }

            $company->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Company $company = null): array
    {
        $user = $request->user();
        $isAdmin = $user->is_platform_admin;
        $sometimes = $company !== null ? ['sometimes'] : [];

        $validated = $request->validate([
            'parent_uuid' => ['nullable', 'string', Rule::exists('companies', 'uuid')],

            // ລະຫັດບໍ່ບັງຄັບອີກຕໍ່ໄປ — ຮ້ານຂາຍອອນລາຍຄວນ "ໃສ່ແຕ່ຊື່ຮ້ານ" ໄດ້.
            // ບໍ່ໄດ້ໃສ່ = ສ້າງໃຫ້ຈາກຊື່ (ເບິ່ງ generateCode)
            'code' => ['nullable', 'string', 'max:30',
                Rule::unique('companies', 'code')->ignore($company)],
            'name' => [...$sometimes, 'required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],

            // ຜູ້ໃຊ້ທຳມະດາສ້າງໄດ້ແຕ່ສາຂາ/ຮ້ານ — ອົງກອນລະດັບເທິງທີ່ຖືແພັກເກັດ
            // ຍັງເປັນວຽກຂອງຜູ້ດູແລລະບົບ
            'type' => ['nullable', Rule::in($isAdmin ? Company::TYPES : Company::SELF_SERVICE_TYPES)],

            'plan' => ['nullable', Rule::in(Company::PLANS)],
            'trial_ends_at' => ['nullable', 'date'],
            'access_expires_at' => ['nullable', 'date'],

            'tax_id' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'code.unique' => 'ມີລະຫັດອົງກອນນີ້ຢູ່ແລ້ວ',
            'parent_uuid.exists' => 'ບໍ່ພົບອົງກອນແມ່ທີ່ລະບຸ',
            'type.in' => 'ສ້າງໄດ້ສະເພາະ ສາຂາ ຫຼື ຮ້ານ ເທົ່ານັ້ນ',
        ]);

        if (! $isAdmin) {
            $validated = $this->applySelfServiceLimits($validated, $user, $company);
        }

        $validated = $this->resolveParent($validated, $company);

        // ສ້າງລະຫັດໃຫ້ຕອນສ້າງໃໝ່ ຖ້າຜູ້ໃຊ້ບໍ່ໄດ້ໃສ່. ຕອນແກ້ໄຂປ່ອຍໄວ້ຄືເກົ່າ —
        // ລະຫັດທີ່ປ່ຽນເອງຈະເຮັດໃຫ້ເອກະສານທີ່ພິມໄປແລ້ວອ້າງອີງບໍ່ຖືກ
        if ($company === null && ($validated['code'] ?? null) === null) {
            $validated['code'] = $this->generateCode($validated['name']);
        }

        return $validated;
    }

    /**
     * ຂອບເຂດຂອງຜູ້ໃຊ້ທີ່ບໍ່ແມ່ນຜູ້ດູແລລະບົບ.
     *
     * ສອງຢ່າງ:
     *   1. ຕັດຊ່ອງທີ່ຄວບຄຸມການເກັບເງິນອອກ — ນີ້ຄືເຫດຜົນທັງໝົດທີ່ CompanyPolicy
     *      ເຄີຍປິດການຂຽນໄວ້: ຖ້າອົງກອນແກ້ຂໍ້ມູນຕົນເອງໄດ້ ກໍຕໍ່ອາຍຸ trial
     *      ໃຫ້ຕົນເອງໄດ້ນຳ.
     *   2. ບັງຄັບໃຫ້ອົງກອນແມ່ຢູ່ໃນຕົ້ນໄມ້ຂອງຕົນ (ຄ່າເລີ່ມຕົ້ນ = ອົງກອນຕົນເອງ)
     *      ບໍ່ດັ່ງນັ້ນຈະເອົາຮ້ານໄປແປະໃສ່ຕົ້ນໄມ້ຂອງລູກຄ້າລາຍອື່ນໄດ້.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applySelfServiceLimits(array $validated, User $user, ?Company $company): array
    {
        unset($validated['plan'], $validated['trial_ends_at'], $validated['access_expires_at']);

        // ສ້າງໃໝ່ໂດຍບໍ່ລະບຸປະເພດ = ຮ້ານ (ກໍລະນີທີ່ພົບຫຼາຍທີ່ສຸດ)
        if ($company === null) {
            $validated['type'] ??= 'store';
        }

        $own = $user->company;

        if ($own === null) {
            throw ValidationException::withMessages([
                'parent_uuid' => 'ບັນຊີຂອງທ່ານຍັງບໍ່ໄດ້ຈັດສັນເຂົ້າອົງກອນໃດ',
            ]);
        }

        $parentUuid = $validated['parent_uuid'] ?? null;

        if ($parentUuid === null) {
            // ບໍ່ໄດ້ເລືອກ = ແປະໃສ່ອົງກອນຕົນເອງ. ນີ້ຄືສິ່ງທີ່ເຮັດໃຫ້ຟອມ
            // "ໃສ່ແຕ່ຊື່ຮ້ານ" ເປັນໄປໄດ້
            $validated['parent_uuid'] = $own->uuid;

            return $validated;
        }

        $parent = Company::query()->where('uuid', $parentUuid)->first();

        if ($parent === null || ! str_starts_with($parent->path, $own->path)) {
            throw ValidationException::withMessages([
                'parent_uuid' => 'ເລືອກໄດ້ສະເພາະອົງກອນ ຫຼື ສາຂາຂອງທ່ານເອງ',
            ]);
        }

        return $validated;
    }

    /**
     * ສ້າງລະຫັດຈາກຊື່ ພ້ອມກັນຊ້ຳ.
     *
     * ⚠️ `Str::slug()` ຕັດຕົວອັກສອນລາວອອກໝົດ ("ຮ້ານ ອອນລາຍ" → "") ເຊິ່ງເປັນ
     * ກໍລະນີປົກຕິຂອງລະບົບນີ້ ບໍ່ແມ່ນກໍລະນີແປກ — ຈຶ່ງຕົກມາໃຊ້ຄຳວ່າ STORE
     * ແລ້ວຕໍ່ດ້ວຍເລກ. ລະຫັດເປັນພຽງຕົວອ້າງອີງພາຍໃນ ຜູ້ໃຊ້ເຫັນຊື່ຢູ່ແລ້ວ.
     *
     * ຊື່ລາວທີ່ມີຕົວອັກສອນລາຕິນປົນ ("ຮ້ານ ອອນລາຍ A") ຈະເຫຼືອແຕ່ສ່ວນລາຕິນ —
     * ສັ້ນກວ່າ MIN_CODE_LENGTH ຖືວ່າສັ້ນເກີນໄປທີ່ຈະເປັນຕົວອ້າງອີງ ("A")
     * ຈຶ່ງໃຊ້ STORE ແທນ.
     */
    private function generateCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, ''));
        $base = Str::length($base) < self::MIN_CODE_LENGTH
            ? 'STORE'
            : Str::substr($base, 0, 20);

        if (! Company::query()->where('code', $base)->exists()) {
            return $base;
        }

        // ເລີ່ມທີ່ 2 ("STORE" ແລ້ວ "STORE-2") — ອ່ານເປັນລຳດັບທີ່ຄົນຄາດເດົາໄດ້
        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $candidate = "{$base}-{$suffix}";

            if (! Company::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // ຫຼາຍກວ່າ 999 ຊື່ຊ້ຳກັນ — ຕົກມາໃຊ້ຄ່າສຸ່ມແທນທີ່ຈະລົ້ມ
        return Str::upper(Str::random(12));
    }

    /**
     * ແປງ parent_uuid → parent_id ພ້ອມກັນ 2 ຢ່າງທີ່ເຮັດໃຫ້ຕົ້ນໄມ້ພັງ:
     * ວົງຈອນ (ເອົາລູກຫຼານມາເປັນພໍ່ແມ່) ແລະ ຄວາມເລິກເກີນຂອບເຂດ.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function resolveParent(array $validated, ?Company $company): array
    {
        if (! array_key_exists('parent_uuid', $validated)) {
            return $validated;
        }

        $parentUuid = $validated['parent_uuid'];
        unset($validated['parent_uuid']);

        if ($parentUuid === null) {
            $validated['parent_id'] = null;

            return $validated;
        }

        $parent = Company::query()->where('uuid', $parentUuid)->firstOrFail();

        if ($company !== null) {
            // ຕົນເອງ ຫຼື ລູກຫຼານຂອງຕົນເອງ ມາເປັນພໍ່ແມ່ບໍ່ໄດ້ — ຈະເກີດວົງຈອນ
            // ທີ່ເຮັດໃຫ້ການຂຽນ path ຄືນວົນບໍ່ຮູ້ຈົບ
            $isSelfOrDescendant = $parent->is($company)
                || str_starts_with($parent->path, $company->path);

            if ($isSelfOrDescendant) {
                throw ValidationException::withMessages([
                    'parent_uuid' => 'ເລືອກອົງກອນນີ້ ຫຼື ສາຂາລູກຂອງມັນ ມາເປັນອົງກອນແມ່ບໍ່ໄດ້',
                ]);
            }
        }

        if ($parent->depth + 1 > Company::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_uuid' => 'ຊັ້ນຂອງສາຂາເລິກເກີນ '.Company::MAX_DEPTH.' ຊັ້ນແລ້ວ',
            ]);
        }

        $validated['parent_id'] = $parent->id;

        return $validated;
    }
}

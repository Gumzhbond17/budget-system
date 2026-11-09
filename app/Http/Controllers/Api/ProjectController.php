<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Money\Money;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ໂຄງການ — ຂັ້ນທີສອງຂອງຂັ້ນຕອນ "ເລືອກອົງກອນ → ເລືອກໂຄງການ → ມອບສິດຜູ້ໃຊ້".
 */
final class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        $projects = Project::query()
            // ຫົວໃຈຂອງການກັນ IDOR: ຜູ້ໃຊ້ທຳມະດາເຫັນສະເພາະໂຄງການທີ່ຕົນເປັນສະມາຊິກ
            ->unless(
                $user->is_platform_admin,
                fn ($q) => $q->whereIn('id', array_keys($user->projectRoles()))
            )
            ->when($request->filled('company_uuid'), function ($q) use ($request) {
                $q->whereHas('company', fn ($c) => $c->where('uuid', $request->string('company_uuid')));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';

                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->with('company')
            ->withCount(['members', 'expenses'])
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return ProjectResource::collection($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $data = $this->validated($request);
        $data['uuid'] = (string) Str::uuid7();
        $data['created_by'] = $request->user()->id;

        $project = DB::transaction(function () use ($data, $request): Project {
            $project = Project::query()->create($data);

            // ໂຄງການທີ່ເກີດມາໂດຍບໍ່ມີສະມາຊິກ = ໂຄງການທີ່ບໍ່ມີໃຜເຂົ້າໄດ້ເລີຍ:
            // ExpensePolicy ແລະ RevenuePolicy ບັງຄັບການເປັນສະມາຊິກ ແລະ ບໍ່ມີ
            // before() ໃຫ້ຜູ້ດູແລລະບົບຂ້າມ (ໂດຍເຈດຕະນາ — ຂໍ້ມູນການເງິນ).
            // ProjectMemberController ກັນບໍ່ໃຫ້ຖອດ full_access ຄົນສຸດທ້າຍຢູ່ແລ້ວ
            // ແຕ່ບໍ່ມີບ່ອນໃດຕັ້ງຄົນນັ້ນຂຶ້ນມາ — ນີ້ຄືບ່ອນນັ້ນ.
            $project->members()->create([
                'user_id' => $request->user()->id,
                'role' => 'full_access',
            ]);

            return $project;
        });

        return ProjectResource::make($project->load('company')->loadCount(['members', 'expenses']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return ProjectResource::make(
            $project->load('company')->loadCount(['members', 'expenses'])
        );
    }

    public function update(Request $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($this->validated($request, $project));

        return ProjectResource::make($project->load('company')->loadCount(['members', 'expenses']));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Project $project = null): array
    {
        $sometimes = $project !== null ? ['sometimes'] : [];

        $validated = $request->validate([
            'company_uuid' => [...$sometimes, 'required', 'string', Rule::exists('companies', 'uuid')],
            'code' => [...$sometimes, 'required', 'string', 'max:30'],
            'name' => [...$sometimes, 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity_kw' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_currency_code' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            // ຮັບເປັນຂໍ້ຄວາມ ("1,493,145.00") ແລ້ວແປງຜ່ານ Money —
            // ຫ້າມຮັບເປັນ float ເດັດຂາດ (docs/architecture.md ພາກ 2.1.ກ)
            'budget_amount' => ['nullable', 'string'],
            'status' => ['nullable', 'in:planning,active,on_hold,completed,cancelled'],
        ], [
            'company_uuid.exists' => 'ບໍ່ພົບອົງກອນທີ່ລະບຸ',
        ]);

        $companyId = $this->resolveCompanyId($validated);
        unset($validated['company_uuid']);

        if ($companyId !== null) {
            $validated['company_id'] = $companyId;
        }

        $this->assertCodeIsUniqueInCompany(
            $validated['code'] ?? $project?->code,
            $companyId ?? $project?->company_id,
            $project,
        );

        if (array_key_exists('budget_amount', $validated)) {
            $currency = $this->resolveBudgetCurrency($validated, $project);

            $validated['budget_amount_minor'] = $validated['budget_amount'] === null
                ? 0
                : Money::fromDecimal(
                    $validated['budget_amount'],
                    $currency,
                    Currency::decimalsFor($currency),
                )->minor;

            unset($validated['budget_amount']);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCompanyId(array $validated): ?int
    {
        if (! array_key_exists('company_uuid', $validated)) {
            return null;
        }

        return (int) Company::query()->where('uuid', $validated['company_uuid'])->value('id');
    }

    /**
     * ສະກຸນເງິນທີ່ຈະໃຊ້ແປງງົບປະມານ: ຄ່າທີ່ສົ່ງມາ → ຄ່າເກົ່າຂອງໂຄງການ → USD.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveBudgetCurrency(array $validated, ?Project $project): string
    {
        if (isset($validated['budget_currency_code'])) {
            return (string) $validated['budget_currency_code'];
        }

        return $project !== null ? $project->budget_currency_code : 'USD';
    }

    /**
     * `code` unique ຕໍ່ອົງກອນ (ບໍ່ແມ່ນທົ່ວລະບົບ) — Rule::unique ທຳມະດາ
     * ເຮັດເງື່ອນໄຂສອງຄໍລຳພ້ອມ ignore ຕົນເອງໄດ້ຍາກ ຈຶ່ງກວດເອງໃຫ້ຂໍ້ຄວາມຊັດເຈນ.
     */
    private function assertCodeIsUniqueInCompany(
        ?string $code,
        ?int $companyId,
        ?Project $project,
    ): void {
        if ($code === null || $companyId === null) {
            return;
        }

        $exists = Project::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->when($project !== null, fn ($q) => $q->whereKeyNot($project->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'ມີໂຄງການລະຫັດນີ້ຢູ່ໃນອົງກອນນີ້ແລ້ວ',
            ]);
        }
    }
}

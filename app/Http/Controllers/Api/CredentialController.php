<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CredentialResource;
use App\Models\ActivityLog;
use App\Models\Credential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * ບັນຊີເຂົ້າໃຊ້ລະບົບຕ່າງໆ — ສະເພາະຜູ້ດູແລລະບົບ (CredentialPolicy).
 *
 * ຕ່າງຈາກ controller ຂໍ້ມູນຫຼັກອື່ນ ຢູ່ 3 ຈຸດ:
 *
 * 1. **ລະຫັດບໍ່ເຄີຍອອກໄປກັບລາຍການ** — CredentialResource ຄືນແຕ່ຄ່າແທນ.
 *    ຄ່າຈິງອອກທາງ `reveal()` ເທົ່ານັ້ນ ແລະ ຕ້ອງພິມລະຫັດຜ່ານຂອງຕົນເອງກ່ອນ.
 *
 * 2. **ບໍ່ມີ 409 ຕອນລຶບ** — ບໍ່ມີຕາຕະລາງໃດອ້າງເຖິງແຖວນີ້ ຈຶ່ງບໍ່ມີຫຍັງໃຫ້
 *    ກວດ. ລຶບແລ້ວ ciphertext ຫາຍໄປແທ້ (ບໍ່ມີ softDeletes ໂດຍເຈດຕະນາ).
 *
 * 3. **ບໍ່ແບ່ງໜ້າ** — ຄືກັບທະນາຄານ: ຈຳນວນບັນຊີມີບໍ່ຫຼາຍ ແລະ ໜ້າຈັດການ
 *    ຕ້ອງການທັງໝົດເພື່ອກັ່ນຕອງຢູ່ຝັ່ງ browser.
 */
final class CredentialController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Credential::class);

        $credentials = Credential::query()
            // subquery ອັນດຽວ ບໍ່ແມ່ນ query ຕໍ່ແຖວ — ໜ້າຈໍໃຊ້ບອກວ່າລະຫັດ
            // ອັນນີ້ຖືກເປີດເບິ່ງຄັ້ງສຸດທ້າຍເມື່ອໃດ
            ->withMax(
                ['activityLogs as last_revealed_at' => fn ($q) => $q->where('event', 'revealed')],
                'created_at'
            )
            // ໜ້າຈັດການຄືບ່ອນດຽວທີ່ຕ້ອງເຫັນແຖວທີ່ຢຸດໃຊ້ ເພື່ອເປີດກັບຄືນໄດ້
            ->when(! $request->has('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';

                // ⚠️ ບໍ່ຄົ້ນຫາໃນ `password`: ຄ່າໃນ DB ເປັນ ciphertext ຈຶ່ງ
                // LIKE ບໍ່ໄດ້ຢູ່ແລ້ວ — ແລະ ຖ້າໄດ້ກໍຈະກາຍເປັນຊ່ອງທາງເດົາລະຫັດ
                // ດ້ວຍການພິມທີ່ລະຕົວແລ້ວເບິ່ງວ່າແຖວໃດຍັງເຫຼືອ
                $q->where(fn ($sub) => $sub
                    ->where('title', 'like', $term)
                    ->orWhere('url_link', 'like', $term)
                    ->orWhere('username', 'like', $term));
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return CredentialResource::collection($credentials);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Credential::class);

        $credential = new Credential($this->validated($request));

        // ຕັ້ງນອກ mass assignment ໂດຍເຈດຕະນາ — `created_by` ບໍ່ຢູ່ໃນ Fillable
        // ຈຶ່ງບໍ່ມີທາງທີ່ client ຈະສົ່ງມາເອງວ່າ "ຄົນອື່ນເປັນຜູ້ສ້າງ"
        $credential->created_by = $request->user()->id;
        $credential->save();

        return CredentialResource::make($credential)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Credential $credential): CredentialResource
    {
        $this->authorize('view', $credential);

        return CredentialResource::make($credential->load('creator'));
    }

    public function update(Request $request, Credential $credential): CredentialResource
    {
        $this->authorize('update', $credential);

        $data = $this->validated($request, $credential);

        // ຟອມແກ້ໄຂເຕີມລະຫັດເກົ່າຄືນໃຫ້ບໍ່ໄດ້ (ບໍ່ເຄີຍໄດ້ຮັບມັນ) ຈຶ່ງສົ່ງ
        // ຊ່ອງຫວ່າງມາເມື່ອບໍ່ໄດ້ຕັ້ງໃຈປ່ຽນ — ຖ້າຂຽນທັບຕາມນັ້ນ ການແກ້ຊື່
        // ເສີຍໆຈະລຶບລະຫັດຖິ້ມແບບງຽບໆ
        if (($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        $credential->update($data);

        return CredentialResource::make($credential);
    }

    public function destroy(Credential $credential): JsonResponse
    {
        $this->authorize('delete', $credential);

        $credential->delete();

        return response()->json(null, 204);
    }

    /**
     * ເປີດເບິ່ງລະຫັດຈິງ — ຕ້ອງພິມ **ລະຫັດຜ່ານຂອງຕົນເອງ** ຢືນຢັນກ່ອນ.
     *
     * ເປັນຫຍັງຕ້ອງຖາມຊ້ຳທັງທີ່ login ຢູ່ແລ້ວ: ໄພຄຸກຄາມທີ່ແທ້ຈິງຂອງໜ້ານີ້
     * ບໍ່ແມ່ນຄົນນອກ (policy ກັນຢູ່ແລ້ວ) ແຕ່ແມ່ນຄົນທີ່ຍ່າງມາໃຊ້ໜ້າຈໍທີ່ຜູ້
     * ດູແລລະບົບເປີດຄ້າງໄວ້. ການຖາມລະຫັດຄືສິ່ງດຽວທີ່ແຍກ "ຜູ້ໃຊ້ຄົນນີ້" ອອກ
     * ຈາກ "ຄົນທີ່ນັ່ງຢູ່ຄອມພິວເຕີເຄື່ອງນີ້".
     *
     * ⚠️ ຄືນທີ່ລະ 1 ລະຫັດເທົ່ານັ້ນ ບໍ່ມີ endpoint ທີ່ຄືນທັງໝົດພ້ອມກັນ —
     * ບໍ່ດັ່ງນັ້ນການຢືນຢັນເທື່ອດຽວຈະເປີດທຸກຢ່າງ ແລະ audit log ຈະບອກບໍ່ໄດ້
     * ວ່າຜູ້ໃຊ້ຕ້ອງການລະຫັດອັນໃດແທ້.
     */
    public function reveal(Request $request, Credential $credential): JsonResponse
    {
        $this->authorize('reveal', $credential);

        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'ກະລຸນາປ້ອນລະຫັດຜ່ານຂອງທ່ານ',
        ]);

        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            // ບັນທຶກການພິມຜິດນຳ: ຄົນທີ່ພະຍາຍາມເປີດແຕ່ພິມລະຫັດບໍ່ຖືກ ຄືສັນຍານ
            // ທີ່ຕ້ອງເຫັນ ບໍ່ແມ່ນສິ່ງທີ່ຄວນຫາຍໄປງຽບໆ
            ActivityLog::record($credential, 'reveal_failed', $request);

            // 422 ຜູກກັບ field ບໍ່ແມ່ນ 403: ນີ້ຄືການພິມຜິດ ບໍ່ແມ່ນການບໍ່ມີສິດ —
            // ໜ້າຈໍຈຶ່ງສະແດງຂໍ້ຄວາມໃຕ້ຊ່ອງ ແລ້ວໃຫ້ລອງໃໝ່ໄດ້ທັນທີ
            throw ValidationException::withMessages([
                'password' => 'ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ',
            ]);
        }

        ActivityLog::record($credential, 'revealed', $request);

        return response()->json([
            'data' => [
                'uuid' => $credential->uuid,
                // cast `encrypted` ຖອດລະຫັດໃຫ້ຕອນອ່ານ attribute
                'password' => $credential->password,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Credential $credential = null): array
    {
        $sometimes = $credential !== null ? ['sometimes'] : [];

        return $request->validate([
            'title' => [...$sometimes, 'required', 'string', 'max:160'],
            'url_link' => ['nullable', 'string', 'max:500'],
            'username' => ['nullable', 'string', 'max:190'],

            // ຕອນສ້າງຕ້ອງມີ, ຕອນແກ້ໄຂປ່ອຍຫວ່າງໄດ້ = "ຢ່າປ່ຽນລະຫັດ"
            // (ເບິ່ງ `update` — ຊ່ອງຫວ່າງຖືກຖອດອອກກ່ອນບັນທຶກ)
            'password' => $credential !== null
                ? ['sometimes', 'nullable', 'string', 'max:500']
                : ['required', 'string', 'max:500'],

            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'title.required' => 'ກະລຸນາປ້ອນຫົວຂໍ້',
            'password.required' => 'ກະລຸນາປ້ອນລະຫັດຜ່ານຂອງບັນຊີນີ້',
        ]);
    }
}

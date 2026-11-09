<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankLogoRequest;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use App\Services\BankLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ທະນາຄານ — ຂໍ້ມູນຫຼັກທີ່ໃຊ້ຮ່ວມກັນທຸກໂຄງການ ພ້ອມໂລໂກ້ 1 ຮູບຕໍ່ 1 ແຖວ.
 *
 * ຕ່າງຈາກໝວດ/ຜູ້ຈ່າຍ/ຜູ້ຮັບເງິນ ຢູ່ຈຸດດຽວ: **ຍັງບໍ່ມີຕາຕະລາງໃດອ້າງເຖິງ**
 * (`payees.bank_name` ຍັງເປັນ string ອິດສະຫຼະ) ຈຶ່ງບໍ່ມີການກວດ 409 ຕອນລຶບ.
 * ຖ້າມື້ໜ້າເພີ່ມ `payees.bank_id` ຕ້ອງເພີ່ມການກວດນັ້ນຢູ່ `destroy` ນຳ.
 *
 * ບໍ່ແບ່ງໜ້າ: ທະນາຄານໃນລາວມີບໍ່ເຖິງ 50 ແຫ່ງ ແລະ ໜ້າຈັດການຕ້ອງການທັງໝົດ.
 */
final class BankController extends Controller
{
    public function __construct(private BankLogoService $logos) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Bank::class);

        $banks = Bank::query()
            // ໜ້າຈັດການຕ້ອງຮູ້ວ່າແຖວໃດລຶບໄດ້ ກ່ອນຈະສະແດງປຸ່ມລຶບ.
            // ນັບສະເພາະເມື່ອຖືກຂໍ: ຟອມທຸລະກຳດຶງ endpoint ນີ້ທຸກເທື່ອທີ່ເປີດ
            // ເພື່ອເຕີມ dropdown ແລະ ບໍ່ໄດ້ໃຊ້ຄ່ານັບເລີຍ
            ->when($request->boolean('with_usage'), fn ($q) => $q->withCount($this->usageCounts()))
            // ໜ້າຈັດການຄືບ່ອນດຽວທີ່ຕ້ອງເຫັນແຖວທີ່ຢຸດໃຊ້ ເພື່ອເປີດກັບຄືນໄດ້
            ->when(! $request->has('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';

                $q->where(fn ($sub) => $sub
                    ->where('name_lo', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhere('short_name', 'like', $term)
                    ->orWhere('code', 'like', $term));
            })
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return BankResource::collection($banks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Bank::class);

        $bank = Bank::query()->create($this->validated($request));

        return BankResource::make($bank->loadCount($this->usageCounts()))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Bank $bank): BankResource
    {
        $this->authorize('view', $bank);

        return BankResource::make($bank);
    }

    public function update(Request $request, Bank $bank): BankResource
    {
        $this->authorize('update', $bank);

        $bank->update($this->validated($request, $bank));

        return BankResource::make($bank->loadCount($this->usageCounts()));
    }

    /**
     * ນັບການອ້າງອີງ ລວມແຖວທີ່ soft-delete ໄປແລ້ວ — ເຫດຜົນຢູ່ `Bank::usageCount()`
     *
     * @return array<string, \Closure>
     */
    private function usageCounts(): array
    {
        $withTrashed = fn ($query) => $query->withoutGlobalScopes();

        return ['expenses' => $withTrashed, 'revenues' => $withTrashed];
    }

    public function destroy(Bank $bank): JsonResponse
    {
        $this->authorize('delete', $bank);

        $used = $bank->usageCount();

        if ($used > 0) {
            // 409 ບໍ່ແມ່ນ 403: ບໍ່ແມ່ນເລື່ອງສິດ ແຕ່ເປັນສະຖານະຂອງຂໍ້ມູນ.
            // FK ເປັນ nullOnDelete ຈຶ່ງລຶບໄດ້ທາງເຕັກນິກ ແຕ່ຈະເຮັດໃຫ້ໃບໂອນທີ່
            // ແນບໄວ້ກາຍເປັນ "ໂອນຜ່ານທະນາຄານ (ບໍ່ຮູ້)" ແບບງຽບໆທັງໝົດ.
            return response()->json([
                'message' => "ລຶບບໍ່ໄດ້ — ມີ {$used} ລາຍການລະບຸວ່າໂອນຜ່ານທະນາຄານນີ້. "
                           ."ຢຸດໃຊ້ດ້ວຍການປິດ 'ໃຊ້ງານຢູ່' ແທນ.",
            ], 409);
        }

        // ລຶບໄຟລ໌ໂລໂກ້ນຳ — ບໍ່ດັ່ງນັ້ນມັນຈະຄ້າງຢູ່ disk ໂດຍບໍ່ມີໃຜອ້າງເຖິງ
        // ຕະຫຼອດໄປ. ໂລໂກ້ບໍ່ແມ່ນຫຼັກຖານ ຈຶ່ງບໍ່ມີເຫດຜົນໃຫ້ເກັບໄວ້.
        $this->logos->remove($bank);

        $bank->delete();

        return response()->json(null, 204);
    }

    /** ອັບ/ທັບໂລໂກ້ — ຄືນແຖວທັງອັນ ເພື່ອໃຫ້ໜ້າຈໍໄດ້ `logo_url` ໃໝ່ພ້ອມ */
    public function storeLogo(StoreBankLogoRequest $request, Bank $bank): BankResource
    {
        return BankResource::make(
            $this->logos->store($bank, $request->file('logo'))
        );
    }

    public function destroyLogo(Bank $bank): BankResource
    {
        $this->authorize('update', $bank);

        return BankResource::make($this->logos->remove($bank));
    }

    /**
     * Stream ໂລໂກ້. Route ນີ້ມີ middleware 'signed' ແລະ **ບໍ່ມີ** auth —
     * browser ບໍ່ສົ່ງ header auth ໄປກັບ <img src> (ເຫດຜົນດຽວກັບຫຼັກຖານ,
     * ເບິ່ງ AttachmentController::download).
     */
    public function logo(Bank $bank): StreamedResponse
    {
        abort_if(! $bank->hasLogo(), 404);

        $disk = Storage::disk('private');

        abort_unless($disk->exists($bank->logo_path), 404);

        return $disk->response($bank->logo_path, "{$bank->code}.webp", [
            'Content-Type' => 'image/webp',
            // ກັນ browser ຕີຄວາມໄຟລ໌ເປັນ HTML → stored XSS
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.addslashes($bank->code).'.webp"',
            // path ມີ UUID ໃໝ່ທຸກເທື່ອທີ່ອັບ ຈຶ່ງ cache ດົນໆໄດ້ຢ່າງປອດໄພ —
            // ໂລໂກ້ໃໝ່ = URL ໃໝ່ສະເໝີ ບໍ່ມີທາງເຫັນຂອງເກົ່າຄ້າງ
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Bank $bank = null): array
    {
        // ລະຫັດເປັນຕົວພິມໃຫຍ່ສະເໝີ — ບໍ່ດັ່ງນັ້ນ "bcel" ກັບ "BCEL" ຜ່ານ
        // unique ທັງຄູ່ແລ້ວກາຍເປັນສອງທະນາຄານ (ບັນຫາດຽວກັບ "SSS" vs "SSS ")
        if (is_string($code = $request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($code))]);
        }

        if (is_string($swift = $request->input('swift_code'))) {
            $request->merge(['swift_code' => $swift === '' ? null : strtoupper(trim($swift))]);
        }

        $sometimes = $bank !== null ? ['sometimes'] : [];

        return $request->validate([
            'code' => [
                ...$sometimes,
                'required',
                'string',
                'regex:/^[A-Z0-9][A-Z0-9-]{1,19}$/',
                Rule::unique('banks', 'code')->ignore($bank),
            ],
            'name_lo' => [...$sometimes, 'required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:40'],
            // ISO-9362: 8 ຕົວ (ສຳນັກງານໃຫຍ່) ຫຼື 11 ຕົວ (ລະບຸສາຂາ) ເທົ່ານັ້ນ —
            // ຄວາມຍາວອື່ນຄືການພິມຜິດ ບໍ່ແມ່ນຮູບແບບທີ່ຖືກຕ້ອງ
            'swift_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'code.regex' => 'ລະຫັດຕ້ອງເປັນຕົວອັກສອນອັງກິດ/ຕົວເລກ 2–20 ຕົວ ເຊັ່ນ BCEL, LDB, JDB',
            'code.unique' => 'ມີທະນາຄານລະຫັດນີ້ຢູ່ແລ້ວ',
            'swift_code.regex' => 'SWIFT/BIC ຕ້ອງມີ 8 ຫຼື 11 ຕົວ (ຕົວອັກສອນອັງກິດ ແລະ ຕົວເລກ)',
        ]);
    }
}

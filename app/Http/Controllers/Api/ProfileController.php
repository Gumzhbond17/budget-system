<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ໂປຣໄຟລ໌ຂອງຜູ້ໃຊ້ທີ່ login ຢູ່ + ຮູບໂປຣໄຟລ໌.
 *
 * ແຍກຈາກ `GET /user` ເພາະອັນນັ້ນຖືກເອີ້ນທຸກຄັ້ງທີ່ SPA ບູດ ແລະ ບໍ່ຄວນແບກ
 * ລາຍການສິດຕໍ່ໂຄງການທັງໝົດໄປນຳ. ໜ້າໂປຣໄຟລ໌ຕ້ອງການມັນເພື່ອບອກວ່າ
 * "ຂ້ອຍມີສິດຫຍັງໃນໂຄງການໃດ" — ຄຳຖາມທີ່ຜູ້ໃຊ້ຕອບເອງບໍ່ໄດ້ຈາກໜ້າອື່ນ.
 */
final class ProfileController extends Controller
{
    public function __construct(private AvatarService $avatars) {}

    public function show(Request $request): UserResource
    {
        $user = $request->user();

        // UserPolicy::view ຍອມໃຫ້ເບິ່ງຕົນເອງສະເໝີ — ກວດໄວ້ຕາມກົດ "ທຸກ endpoint ຕ້ອງມີ Policy"
        $this->authorize('view', $user);

        return $this->profile($user);
    }

    /** ອັບ/ທັບຮູບ — ຄືນໂປຣໄຟລ໌ທັງອັນ ເພື່ອໃຫ້ໜ້າຈໍໄດ້ `avatar_url` ໃໝ່ພ້ອມ */
    public function storeAvatar(StoreAvatarRequest $request): UserResource
    {
        $user = $request->user();

        $this->avatars->store($user, $request->file('avatar'));

        return $this->profile($user);
    }

    public function destroyAvatar(Request $request): UserResource
    {
        $user = $request->user();

        $this->authorize('updateAvatar', $user);

        $this->avatars->remove($user);

        return $this->profile($user);
    }

    /**
     * ສົ່ງໄຟລ໌ຮູບ — route `signed` ບໍ່ມີ auth ເພາະ browser ບໍ່ສົ່ງ header auth
     * ໄປກັບ <img src> (ເຫດຜົນດຽວກັບ BankController::logo). URL ຖືກສ້າງໃຫ້
     * ສະເພາະຄົນທີ່ UserResource ສະແດງຜູ້ໃຊ້ຄົນນີ້ໃຫ້ຢູ່ແລ້ວ.
     */
    public function avatar(User $user): StreamedResponse
    {
        abort_unless($user->hasAvatar(), 404);

        $disk = Storage::disk('private');

        abort_unless($disk->exists((string) $user->avatar_path), 404);

        return $disk->response((string) $user->avatar_path, "avatar-{$user->id}.webp", [
            'Content-Type' => 'image/webp',
            // ກັນ browser ຕີຄວາມໄຟລ໌ເປັນ HTML → stored XSS
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function profile(User $user): UserResource
    {
        return UserResource::make($user->load([
            'company',
            'projectMemberships' => fn ($query) => $query->latest(),
            'projectMemberships.project:id,uuid,code,name',
        ]));
    }
}

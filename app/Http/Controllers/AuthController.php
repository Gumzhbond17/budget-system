<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA mode — ຢືນຢັນຕົວຕົນດ້ວຍ session cookie (httpOnly) ບໍ່ແມ່ນ token.
 *
 * ຂັ້ນຕອນຝັ່ງ SPA:
 *   1. GET  /sanctum/csrf-cookie   → ໄດ້ XSRF-TOKEN
 *   2. POST /login                 → ໄດ້ session cookie
 *   3. GET  /api/v1/user           → ດຶງຂໍ້ມູນຜູ້ໃຊ້
 */
final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            // ຂໍ້ຄວາມດຽວກັນສະເໝີ — ຫ້າມບອກວ່າ "ບໍ່ພົບອີເມວ" ຫຼື "ລະຫັດຜິດ"
            // ບໍ່ດັ່ງນັ້ນຈະກາຍເປັນຊ່ອງນັບອີເມວທີ່ມີຢູ່ໃນລະບົບ (user enumeration)
            throw ValidationException::withMessages([
                'email' => 'ອີເມວ ຫຼື ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ',
            ]);
        }

        // ກັນ session fixation — ບັງຄັບຫຼັງ login ທຸກຄັ້ງ
        $request->session()->regenerate();

        if (! $remember) {
            $this->endSessionWhenBrowserCloses();
        }

        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * ບໍ່ຕິ໊ກ "ຈື່ຂ້ອຍໄວ້" = ຢູ່ໃນລະບົບແຕ່ຮອດຕອນປິດ browser ເທົ່ານັ້ນ.
     *
     * ຕ້ອງເຮັດສອງຢ່າງ ບໍ່ດັ່ງນັ້ນ checkbox ຈະບໍ່ມີຜົນຫຍັງເລີຍ:
     *   1. cookie ຂອງ session ບໍ່ໃຫ້ມີວັນໝົດອາຍຸ (expire_on_close) —
     *      ຄ່າຕັ້ງຕົ້ນຂອງ Laravel ໃຫ້ 120 ນາທີ ເຊິ່ງຢູ່ຂ້າມການປິດ browser
     *   2. ລຶບ recaller ເກົ່າ — Auth::attempt() ບໍ່ລຶບໃຫ້ ຖ້າຄັ້ງກ່ອນເຄີຍ
     *      ຕິ໊ກໄວ້ cookie ອາຍຸ 5 ປີ ຈະດຶງຜູ້ໃຊ້ກັບເຂົ້າມາຢູ່ດີ
     *
     * ອ່ານ config ຕອນຂຽນ cookie ຈຶ່ງຕັ້ງຢູ່ນີ້ຫຼັງ regenerate() ໄດ້ —
     * StartSession::addCookieToResponse() ເອີ້ນ getSessionConfig() ຕອນຂາອອກ.
     */
    private function endSessionWhenBrowserCloses(): void
    {
        config(['session.expire_on_close' => true]);

        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            Cookie::queue(Cookie::forget($guard->getRecallerName()));
        }
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();   // CSRF token ໃໝ່ໃຫ້ SPA

        return response()->json(['message' => 'ອອກຈາກລະບົບແລ້ວ']);
    }
}

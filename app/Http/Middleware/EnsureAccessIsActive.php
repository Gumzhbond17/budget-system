<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ປິດກັ້ນຜູ້ໃຊ້ທີ່ບັນຊີຖືກປິດ ຫຼື ອົງກອນໝົດອາຍຸ/ຖືກລະງັບ.
 *
 * ວາງໄວ້ **ຫຼັງ** `auth:sanctum` ເທົ່ານັ້ນ — ຕ້ອງມີຜູ້ໃຊ້ກ່ອນຈຶ່ງກວດໄດ້.
 *
 * ຕອບ 403 ພ້ອມ `code` ທີ່ຄົງທີ່ ເພື່ອໃຫ້ SPA ແຍກອອກຈາກ 403 ທຳມະດາ
 * (ສິດບໍ່ພໍ) ແລ້ວສະແດງໜ້າ "ໝົດອາຍຸທົດລອງໃຊ້" ແທນທີ່ຈະເປັນ "ບໍ່ມີສິດ".
 */
final class EnsureAccessIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canAccessSystem()) {
            return response()->json([
                'message' => $this->reasonFor($user),
                'code' => 'access_expired',
                'company' => $user->company?->only(['name', 'plan']),
            ], 403);
        }

        return $next($request);
    }

    private function reasonFor(User $user): string
    {
        if (! $user->is_active) {
            return 'ບັນຊີຂອງທ່ານຖືກປິດການໃຊ້ງານ — ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ';
        }

        $company = $user->company;

        if ($company === null) {
            return 'ບັນຊີຂອງທ່ານຍັງບໍ່ໄດ້ຈັດສັນເຂົ້າອົງກອນໃດ — ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ';
        }

        if ($company->plan === 'suspended' || ! $company->is_active) {
            return "ອົງກອນ {$company->name} ຖືກລະງັບການໃຊ້ງານ — ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ";
        }

        // ອົງກອນຍັງດີ ແຕ່ເຂົ້າບໍ່ໄດ້ = ວັນໝົດອາຍຸຂອງບັນຊີເອງເປັນຕົວກັ້ນ.
        // ຕ້ອງບອກໃຫ້ຖືກເຫດ ບໍ່ດັ່ງນັ້ນຜູ້ໃຊ້ຈະໄປທວງໃຫ້ຕໍ່ອາຍຸອົງກອນທີ່ບໍ່ມີບັນຫາ.
        if ($company->hasActiveAccess()) {
            $date = $user->access_expires_at?->toDateString();

            return "ສິດການໃຊ້ງານຂອງບັນຊີທ່ານໝົດອາຍຸແລ້ວ ({$date}) — ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບເພື່ອຕໍ່ອາຍຸ";
        }

        return $company->plan === 'trial'
            ? "ໄລຍະທົດລອງໃຊ້ຂອງ {$company->name} ໝົດອາຍຸແລ້ວ — ກະລຸນາຕໍ່ອາຍຸເພື່ອໃຊ້ງານຕໍ່"
            : "ສິດການໃຊ້ງານຂອງ {$company->name} ໝົດອາຍຸແລ້ວ — ກະລຸນາຕໍ່ອາຍຸເພື່ອໃຊ້ງານຕໍ່";
    }
}

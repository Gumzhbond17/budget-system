<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Audit\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ບັນທຶກການຄົ້ນຫາ (`?search=`) ທີ່ສົ່ງມາ server ລົງ activity_logs.
 *
 * ⚠️ ໄດ້ສະເພາະການຄົ້ນຫາທີ່ໄປຮອດ server — ຊ່ອງກັ່ນຕອງໃນຕາຕະລາງ AG Grid
 * (ລາຍຈ່າຍ/ລາຍຮັບ) ກັ່ນຢູ່ browser ຈຶ່ງບໍ່ຖືກບັນທຶກ.
 *
 * ກັນຊ້ຳ 5 ນາທີຕໍ່ (ຜູ້ໃຊ້, ໜ້າ, ຄຳຄົ້ນ): useAutoRefresh ຍິງ request ເດີມພ້ອມຄຳຄົ້ນ
 * ເດີມທຸກ 30 ວິນາທີ — ຖ້າບໍ່ກັນ ເປີດໜ້າຄ້າງໄວ້ຊົ່ວໂມງດຽວໄດ້ 120 ແຖວທີ່ຄືກັນໝົດ.
 */
final class LogSearchActivity
{
    private const DEDUPE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $term = trim((string) $request->query('search', ''));
        $user = $request->user();

        if ($term === '' || $user === null || ! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return $response;
        }

        $term = mb_substr($term, 0, 120);
        // "api/v1/projects/{uuid}/expenses" → "projects/{uuid}/expenses"
        $area = (string) preg_replace('#^api/v\d+/#', '', $request->path());

        $key = 'audit:search:'.$user->getAuthIdentifier().':'.sha1($area."\n".$term);

        if (Cache::add($key, true, self::DEDUPE_SECONDS)) {
            ActivityLogger::search($term, $area);
        }

        return $response;
    }
}

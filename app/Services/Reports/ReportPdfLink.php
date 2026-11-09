<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * ສ້າງ URL ທີ່ມີລາຍເຊັນສຳລັບເປີດລາຍງານ PDF ໃນແທັບໃໝ່.
 *
 * ## ເປັນຫຍັງຕ້ອງເຊັນ ບໍ່ໃຊ້ `auth:sanctum` ຊື່ໆ
 * Sanctum SPA mode ຖືວ່າ request ເປັນ "stateful" ກໍຕໍ່ເມື່ອມີ header `Referer`
 * ຫຼື `Origin` ທີ່ກົງກັບ `sanctum.stateful` (ເບິ່ງ EnsureFrontendRequestsAreStateful).
 * ການເປີດ URL ໃນແທັບໃໝ່ **ບໍ່ມີ header ນັ້ນ** ໃນຫຼາຍກໍລະນີທີ່ເກີດເປັນປົກກະຕິ:
 *
 *   - ຜູ້ໃຊ້ກົດໂຫຼດຄືນ (F5) ໃນແທັບ PDF
 *   - ເປີດ URL ຈາກ history / bookmark / ພິມໃສ່ຊ່ອງທີ່ຢູ່
 *   - browser ຕັ້ງ `Referrer-Policy: no-referrer` ຫຼື ມີສ່ວນຂະຫຍາຍປິດ Referer
 *
 * ທຸກກໍລະນີເຫຼົ່ານັ້ນ session ຈະບໍ່ຖືກເປີດເລີຍ → ຕອບ 401 "Unauthenticated."
 * ທັງທີ່ຜູ້ໃຊ້ຍັງ login ຢູ່. ນີ້ຄືບັນຫາຢ່າງດຽວກັນກັບໄຟລ໌ຫຼັກຖານ (ພາກ 4.1)
 * ແລະ ແກ້ດ້ວຍວິທີດຽວກັນ: **ກວດສິດຕອນສ້າງ URL ແລ້ວໃຫ້ລາຍເຊັນເປັນສິດເຂົ້າເຖິງ**.
 *
 * ## ຜູ້ໃຊ້ຖືກຝັງໃນ URL
 * ເສັ້ນທາງທີ່ເຊັນແລ້ວບໍ່ມີ session ຈຶ່ງບໍ່ຮູ້ວ່າໃຜເປີດ. ຝັງ id ໄວ້ໃນ URL —
 * ລາຍເຊັນຮັບປະກັນວ່າບໍ່ຖືກແກ້. ຝັ່ງຮັບຍັງກວດ Policy ຊ້ຳອີກເທື່ອ ເພື່ອວ່າ
 * ຄົນທີ່ຖືກຖອດອອກຈາກໂຄງການແລ້ວຈະໃຊ້ URL ເກົ່າບໍ່ໄດ້.
 */
final class ReportPdfLink
{
    /**
     * @param  array<string, mixed>  $filters  ຕົວກັ່ນຕອງທີ່ validate ແລ້ວ
     */
    public static function make(string $route, Project $project, User $user, array $filters): string
    {
        return URL::temporarySignedRoute(
            $route,
            now()->addMinutes((int) config('pdf.link_ttl_minutes')),
            [
                ...$filters,
                'project' => $project->uuid,
                'user' => $user->id,
            ],
        );
    }
}

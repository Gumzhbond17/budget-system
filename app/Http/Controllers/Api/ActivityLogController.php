<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * ລາຍງານ "ບັນທຶກການໃຊ້ງານ" — ອ່ານຢ່າງດຽວ, ຜູ້ດູແລລະບົບເທົ່ານັ້ນ (ActivityLogPolicy).
 *
 * ຄືນທຸກແຖວໃນຊ່ວງທີ່ກັ່ນ (ສູງສຸດ ROW_CAP) ແລ້ວໃຫ້ຕາຕະລາງແບ່ງໜ້າ/ຮຽງເອງຢູ່
 * browser ຄືກັບໜ້າລາຍຈ່າຍ. ຊ່ວງວັນທີຈຶ່ງບັງຄັບ — ບໍ່ສົ່ງມາ = 7 ວັນລ່າສຸດ.
 */
final class ActivityLogController extends Controller
{
    public const ROW_CAP = 2000;

    public const EVENTS = [
        'login', 'logout', 'login_failed', 'searched',
        'created', 'updated', 'deleted', 'restored',
        'submitted', 'approved', 'rejected',
        'revealed', 'reveal_failed',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ActivityLog::class);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'event' => ['nullable', Rule::in(self::EVENTS)],
            'subject' => ['nullable', Rule::in(array_keys(ActivityLogger::SUBJECTS))],
            'causer_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $from = isset($filters['from'])
            ? Carbon::parse($filters['from'])->startOfDay()
            : now()->subDays(6)->startOfDay();
        $to = isset($filters['to'])
            ? Carbon::parse($filters['to'])->endOfDay()
            : now()->endOfDay();

        $rows = ActivityLog::query()
            ->with('causer:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when(
                $filters['subject'] ?? null,
                fn ($q, $subject) => $q->where('subject_type', ActivityLogger::SUBJECTS[$subject]),
            )
            ->when($filters['causer_id'] ?? null, fn ($q, $id) => $q->where('causer_id', $id))
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = '%'.$term.'%';

                $q->where(fn ($w) => $w
                    ->where('subject_label', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhereHas('causer', fn ($c) => $c
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)));
            })
            ->latest('created_at')
            ->latest('id')
            // +1 ເພື່ອຮູ້ວ່າຖືກຕັດບໍ ໂດຍບໍ່ຕ້ອງ count ແຍກ
            ->limit(self::ROW_CAP + 1)
            ->get();

        $truncated = $rows->count() > self::ROW_CAP;

        return ActivityLogResource::collection($rows->take(self::ROW_CAP))->additional([
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'cap' => self::ROW_CAP,
                // ໜ້າຈໍຕ້ອງບອກຜູ້ໃຊ້ໃຫ້ແຄບຊ່ວງລົງ — ບໍ່ດັ່ງນັ້ນຈະເຂົ້າໃຈວ່າເຫັນຄົບແລ້ວ
                'truncated' => $truncated,
            ],
        ]);
    }
}

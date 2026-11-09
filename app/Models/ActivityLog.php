<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\ActivityLogger;
use App\Policies\ActivityLogPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * ບັນທຶກເຫດການ (audit trail) — docs/architecture.md ພາກ 9.
 *
 * ຜູ້ຂຽນ (ທັງໝົດຜ່ານ App\Audit\ActivityLogger ຍົກເວັ້ນ record()):
 *   - App\Audit\LogsActivity       — ສ້າງ/ແກ້/ລຶບ/ກູ້ຄືນ ຂອງທຸກ model ທີ່ໃຊ້ trait
 *   - App\Audit\AuthActivitySubscriber — login / logout / login ລົ້ມເຫຼວ
 *   - App\Http\Middleware\LogSearchActivity — ການຄົ້ນຫາທີ່ສົ່ງໄປ server
 *   - CredentialController — ການເປີດເບິ່ງລະຫັດ (ບໍ່ປ່ຽນຂໍ້ມູນໃດເລີຍ ຈຶ່ງບໍ່ມີ
 *     ທາງຮູ້ວ່າໃຜເປີດ ຖ້າບໍ່ບັນທຶກໄວ້ຢູ່ນີ້)
 *
 * ⚠️ `updated_at` ບໍ່ມີໃນຕາຕະລາງ ແລະ ບໍ່ຄວນມີ: ແຖວບັນທຶກເຫດການແກ້ບໍ່ໄດ້.
 * ບໍ່ມີ route ແກ້ ຫຼື ລຶບ ໂດຍເຈດຕະນາ.
 *
 * @property int $id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property int|null $causer_id
 * @property string $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable(['subject_type', 'subject_id', 'subject_label', 'causer_id', 'event', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
#[UsePolicy(ActivityLogPolicy::class)]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * ບັນທຶກເຫດການ ພ້ອມ IP ແລະ browser ຂອງ request ປັດຈຸບັນ.
     *
     * `user_agent` ຖືກຕັດທີ່ 255 ຕົວ ຕາມຄວາມກວ້າງຂອງຄໍລຳ — UA ຂອງ browser
     * ຍຸກໃໝ່ຍາວກວ່ານັ້ນໄດ້ ແລະ MySQL ໃນໂໝດ strict ຈະໂຍນ error ແທນທີ່ຈະຕັດ
     * ໃຫ້ເອງ ເຊິ່ງຈະເຮັດໃຫ້ການກະທຳຫຼັກລົ້ມເພາະ log ຂຽນບໍ່ໄດ້.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public static function record(
        Model $subject,
        string $event,
        Request $request,
        ?array $properties = null,
    ): self {
        return self::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'subject_label' => ActivityLogger::labelFor($subject),
            'causer_id' => $request->user()?->getAuthIdentifier(),
            'event' => $event,
            'new_values' => $properties,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Audit\ActivityLogger;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
final class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            // 'expense', 'payee' … ບໍ່ແມ່ນຊື່ class ເຕັມ — ໂຄງສ້າງພາຍໃນບໍ່ຕ້ອງອອກໄປ
            'subject_type' => ActivityLogger::subjectKey($this->subject_type),
            'subject_label' => $this->subject_label,
            'causer' => $this->causer ? [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
                'email' => $this->causer->email,
            ] : null,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

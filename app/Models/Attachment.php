<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Policies\AttachmentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ຫຼັກຖານການຈ່າຍ (polymorphic).
 *
 * `path` ແລະ `disk` ຖືກ hide — ຫ້າມຮົ່ວອອກ API. ການເຂົ້າເຖິງໄຟລ໌ຜ່ານ
 * signed temporary URL ເທົ່ານັ້ນ (docs/architecture.md ພາກ 4.1).
 *
 * @property int $id
 * @property string $uuid
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $thumbnail_path
 * @property string $checksum_sha256
 * @property int $uploaded_by
 */
#[Fillable([
    'uuid',
    'kind',
    'disk',
    'path',
    'thumbnail_path',
    'original_name',
    'mime_type',
    'size_bytes',
    'width',
    'height',
    'checksum_sha256',
    'uploaded_by',
])]
#[Hidden(['disk', 'path', 'thumbnail_path'])]
#[UsePolicy(AttachmentPolicy::class)]
class Attachment extends Model
{
    use LogsActivity;
    use SoftDeletes;

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'ocr_amount_minor' => 'integer',
            'ocr_processed_at' => 'datetime',
        ];
    }
}

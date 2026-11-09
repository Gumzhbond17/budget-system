<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Attachment
 *
 * ⚠️ ບໍ່ມີ `path` ຫຼື `disk` ຢູ່ນີ້ເດັດຂາດ (Model ກໍ #[Hidden] ໄວ້ແລ້ວ) —
 * ທາງດຽວທີ່ຈະເຫັນໄຟລ໌ຄື signed URL ທີ່ໝົດອາຍຸໄວ.
 */
final class AttachmentResource extends JsonResource
{
    /** ອາຍຸ URL ຮູບເຕັມ (ວິນາທີ) — ສັ້ນພໍທີ່ URL ຮົ່ວກໍໃຊ້ບໍ່ໄດ້ດົນ */
    private const FULL_TTL = 300;

    /** thumbnail ຢູ່ດົນກວ່າ — ໜ້າ grid ເປີດຄ້າງໄວ້ໄດ້ໂດຍບໍ່ພັງ */
    private const THUMB_TTL = 1800;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'kind' => $this->kind,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'is_image' => $this->isImage(),

            // ຄືນ URL ພ້ອມໃຊ້ເລີຍ — ບໍ່ດັ່ງນັ້ນ Vue ຕ້ອງຍິງອີກ 1 request ຕໍ່ 1 ຮູບ
            'url' => $this->signedRoute('attachments.download', self::FULL_TTL),
            'thumb_url' => $this->thumbnail_path === null
                ? null
                : $this->signedRoute('attachments.thumbnail', self::THUMB_TTL),
            'url_expires_in' => self::FULL_TTL,

            // 8 ຕົວທຳອິດພຽງພໍໃຫ້ຜູ້ກວດສອບປຽບທຽບດ້ວຍຕາ ໂດຍບໍ່ລົ້ນໜ້າຈໍ
            'checksum_short' => substr($this->checksum_sha256, 0, 8),

            'uploaded_at' => $this->created_at?->toIso8601String(),
            'uploader' => $this->whenLoaded('uploader', fn (): array => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
        ];
    }

    private function signedRoute(string $name, int $ttl): string
    {
        return URL::temporarySignedRoute($name, now()->addSeconds($ttl), [
            'attachment' => $this->uuid,
        ]);
    }
}

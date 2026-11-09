<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\HasEvidence;
use App\Models\Attachment;
use App\Models\User;

/**
 * ຫຼັກຖານສືບທອດສິດຈາກ record ທີ່ມັນຜູກຢູ່ — ຫ້າມກວດແຕ່ `uploaded_by`
 * ບໍ່ດັ່ງນັ້ນຄົນນອກໂຄງການທີ່ຮູ້ uuid ຈະດຶງໃບໂອນທີ່ມີເລກບັນຊີໄດ້.
 */
final class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return $this->isMemberOfOwningProject($user, $attachment);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        // ລາຍການທີ່ອະນຸມັດແລ້ວ = ຫຼັກຖານຖືກ freeze ໄປນຳ.
        // ກວດຜ່ານ interface ຈຶ່ງຄຸມທັງລາຍຈ່າຍ ແລະ ລາຍຮັບ (ແລະ ປະເພດໃໝ່
        // ໃນອະນາຄົດ) ໂດຍອັດຕະໂນມັດ — ບໍ່ຕ້ອງຈື່ມາເພີ່ມຢູ່ນີ້ອີກ
        if ($attachable instanceof HasEvidence && $attachable->isLocked()) {
            return false;
        }

        return $this->isMemberOfOwningProject($user, $attachment)
            && ($attachment->uploaded_by === $user->id
                || $this->hasOwnerRole($user, $attachment));
    }

    private function isMemberOfOwningProject(User $user, Attachment $attachment): bool
    {
        $projectId = $this->projectIdFor($attachment);

        return $projectId !== null && $user->isMemberOf($projectId);
    }

    private function hasOwnerRole(User $user, Attachment $attachment): bool
    {
        $projectId = $this->projectIdFor($attachment);

        return $projectId !== null && $user->hasProjectRole($projectId, ['full_access', 'modify']);
    }

    private function projectIdFor(Attachment $attachment): ?int
    {
        $attachable = $attachment->attachable;

        return $attachable instanceof HasEvidence ? $attachable->projectId() : null;
    }
}

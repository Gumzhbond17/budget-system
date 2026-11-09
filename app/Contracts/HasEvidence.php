<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * ລາຍການທີ່ແນບຫຼັກຖານໄດ້ — ປັດຈຸບັນຄື Expense ແລະ Revenue.
 *
 * ມີ interface ນີ້ແທນທີ່ຈະ type ເປັນ `Model` ລ້າໆ ເພື່ອໃຫ້ AttachmentService
 * ແລະ AttachmentPolicy ບອກໄດ້ຊັດເຈນວ່າມັນຕ້ອງການຫຍັງ. ຖ້າ type ເປັນ Model
 * ເສີຍໆ ການເອົາ model ທີ່ບໍ່ມີສິ່ງເຫຼົ່ານີ້ມາໃສ່ຈະລົ້ມຕອນ runtime
 * ແທນທີ່ຈະຖືກຈັບຕອນວິເຄາະ.
 *
 * ⚠️ ໃຊ້ **method ຈິງ** ບໍ່ແມ່ນ `@property` magic: `@property` ໃນ interface
 * ພິສູດບໍ່ໄດ້ດ້ວຍ static analysis ຈຶ່ງກາຍເປັນສັນຍາທີ່ບໍ່ມີໃຜບັງຄັບ.
 *
 * ຜູ້ implement ຕ້ອງເປັນ Eloquent model ນຳ — ບ່ອນທີ່ຕ້ອງການທັງສອງຈະ
 * ລະບຸເປັນ `Model&HasEvidence` ໃນ docblock.
 */
interface HasEvidence
{
    /** @return MorphMany<Attachment, covariant Model> */
    public function attachments(): MorphMany;

    /** ອະນຸມັດແລ້ວ = ຫຼັກຖານຖືກ freeze ໄປນຳ */
    public function isLocked(): bool;

    /** ໂຄງການທີ່ລາຍການນີ້ສັງກັດ — AttachmentPolicy ໃຊ້ກວດການເປັນສະມາຊິກ */
    public function projectId(): int;
}

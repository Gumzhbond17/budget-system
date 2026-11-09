<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ສະມາຊິກໂຄງການ — ຫົວໃຈຂອງການປ້ອງກັນ IDOR (docs/architecture.md ພາກ 9):
 * ຜູ້ໃຊ້ເຫັນສະເພາະໂຄງການທີ່ຕົນເປັນສະມາຊິກ.
 *
 * ນີ້ຄືບ່ອນທີ່ "ລະດັບສິດ" ຂອງຜູ້ໃຊ້ຖືກກຳນົດ — ຕໍ່ໂຄງການ ບໍ່ແມ່ນຕໍ່ບັນຊີ.
 * ຜູ້ໃຊ້ຄົນດຽວຈຶ່ງເປັນ modify ຢູ່ໂຄງການ A ແລະ read_only ຢູ່ໂຄງການ B ໄດ້.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $role full_access|authorize|modify|read_only
 */
#[Fillable(['project_id', 'user_id', 'role'])]
class ProjectMember extends Model
{
    use LogsActivity;

    /**
     * ລະດັບສິດ ຮຽງຈາກສູງຫາຕ່ຳ:
     *
     *   full_access ເຂົ້າເຖິງເຕັມ  — ຈັດການໂຄງການ, ສະມາຊິກ, ອະນຸມັດ, ແກ້ໄຂ
     *   authorize   ອະນຸມັດ       — ອະນຸມັດ/ປະຕິເສດລາຍຈ່າຍ ແຕ່ບໍ່ໄດ້ສ້າງເອງ
     *   modify      ແກ້ໄຂ         — ສ້າງ/ແກ້ລາຍຈ່າຍ ແລະ ງົບປະມານ ແຕ່ອະນຸມັດບໍ່ໄດ້
     *   read_only   ອ່ານຢ່າງດຽວ   — ເບິ່ງ ແລະ ອອກລາຍງານເທົ່ານັ້ນ
     */
    public const ROLES = ['full_access', 'authorize', 'modify', 'read_only'];

    /** ສິດທີ່ຂຽນຂໍ້ມູນໄດ້ — ໃຊ້ຊ້ຳຫຼາຍບ່ອນໃນ policy */
    public const WRITE_ROLES = ['full_access', 'modify'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ຊື່ຢູ່ໜ້າບັນທຶກການໃຊ້ງານ: "SOLAR-01 · phanh@example.com" */
    public function activityLabel(): string
    {
        return $this->project->code.' · '.$this->user->email;
    }
}

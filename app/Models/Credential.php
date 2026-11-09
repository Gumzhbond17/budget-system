<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Policies\CredentialPolicy;
use Database\Factories\CredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ບັນຊີເຂົ້າໃຊ້ລະບົບຕ່າງໆ — ສະເພາະຜູ້ດູແລລະບົບ (ເບິ່ງ CredentialPolicy).
 *
 * ⚠️ `password` ຖືກ #[Hidden] ຄືກັບ Bank::$logo_path: ຄ່າຈິງບໍ່ເຄີຍອອກໄປ
 * ກັບ resource ໃດເລີຍ — ທາງດຽວທີ່ຈະເຫັນມັນຄື POST .../reveal ພ້ອມລະຫັດ
 * ຜ່ານຂອງຕົນເອງ (CredentialController::reveal).
 *
 * ⚠️ cast ເປັນ `encrypted` ບໍ່ແມ່ນ `hashed` ຄື User::$password — ລະຫັດຂອງ
 * ລະບົບອື່ນຕ້ອງເອົາໄປໃຊ້ຕໍ່ໄດ້ ຈຶ່ງຕ້ອງເປັນການເຂົ້າລະຫັດສອງທາງ. ນີ້ແປວ່າ
 * ຄວາມປອດໄພຂອງແຖວເຫຼົ່ານີ້ຂຶ້ນກັບ APP_KEY ທັງໝົດ:
 *   - APP_KEY ຫຼຸດອອກໄປ = ຜູ້ທີ່ໄດ້ dump ຂອງ DB ອ່ານໄດ້ໝົດ
 *   - APP_KEY ຫາຍ/ຖືກປ່ຽນ = ບໍ່ມີໃຜອ່ານໄດ້ອີກເລີຍ ລວມທັງເຈົ້າຂອງ
 *
 * ໝາຍເຫດ: `last_revealed_at` ຂ້າງລຸ່ມບໍ່ແມ່ນຄໍລຳຈິງ — ເປັນຄ່າ aggregate ທີ່ມີ
 * ສະເພາະເມື່ອ query ຂໍຜ່ານ `withMax('activityLogs as last_revealed_at', ...)`
 * ຈຶ່ງບໍ່ຜ່ານ cast ໃດ ແລະ ອອກມາເປັນ string ດິບຂອງ MySQL
 * (ເບິ່ງ CredentialController::index).
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string|null $url_link
 * @property string|null $username
 * @property string $password
 * @property string|null $note
 * @property bool $is_active
 * @property int $sort_order
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $last_revealed_at
 */
#[Fillable(['title', 'url_link', 'username', 'password', 'note', 'is_active', 'sort_order'])]
#[Hidden(['password'])]
#[UsePolicy(CredentialPolicy::class)]
class Credential extends Model
{
    /** @use HasFactory<CredentialFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * ຄ່າເລີ່ມຕົ້ນຊ້ຳກັບ `default()` ຂອງ migration ໂດຍເຈດຕະນາ.
     *
     * ຖ້າມີແຕ່ຝັ່ງ DB, ແຖວທີ່ຫາກໍ່ສ້າງແລ້ວຄືນອອກໄປທັນທີ (ບໍ່ໄດ້ດຶງຄືນ) ຈະມີ
     * `is_active = null` ແລ້ວ badge ຢູ່ຕາຕະລາງຈະຂຶ້ນວ່າ "ຢຸດໃຊ້" ຈົນກວ່າຈະ
     * ໂຫຼດໜ້າຄືນ. ວາງໄວ້ນີ້ດີກວ່າ `refresh()` ເພາະບໍ່ຕ້ອງຕີ DB ຮອບສອງ.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * ປະຫວັດເຫດການຂອງແຖວນີ້ — ປັດຈຸບັນມີແຕ່ `revealed`.
     *
     * ໃຊ້ເປັນ aggregate (`withMax`) ຢູ່ໜ້າລາຍການ ບໍ່ແມ່ນ eager load:
     * ໜ້າຈໍຕ້ອງການແຕ່ "ເປີດເບິ່ງເທື່ອຫຼ້າສຸດເມື່ອໃດ" ບໍ່ແມ່ນທຸກແຖວ log.
     *
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /**
     * ຄ່າແທນທີ່ສະແດງຢູ່ຕາຕະລາງ — ຈຳນວນຈຸດຄົງທີ່ ບໍ່ແມ່ນຕາມຄວາມຍາວຈິງ.
     *
     * ຖ້າຄວາມຍາວຂອງຈຸດປ່ຽນຕາມລະຫັດ ຄົນທີ່ຫຼຽວຜ່ານໜ້າຈໍຈະຮູ້ວ່າລະຫັດອັນໃດ
     * ສັ້ນພໍທີ່ຈະລອງເດົາ — ຮົ່ວຂໍ້ມູນໂດຍບໍ່ໄດ້ສະແດງຕົວອັກສອນໃດເລີຍ.
     */
    public const MASK = '••••••••';

    protected static function booted(): void
    {
        static::creating(function (self $credential): void {
            $credential->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

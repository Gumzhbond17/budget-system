<?php

namespace App\Models;

use App\Audit\LogsActivity;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Casts\DateOnly;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $email
 * @property string|null $avatar_path ຕັ້ງໂດຍ AvatarService ເທົ່ານັ້ນ (ບໍ່ຢູ່ໃນ Fillable)
 * @property Carbon|null $avatar_updated_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_platform_admin
 * @property bool $is_active
 * @property Carbon|null $access_expires_at
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'company_id',
    'name',
    'email',
    'password',
    'is_platform_admin',
    'is_active',
    'access_expires_at',
])]
// avatar_path: ທາງດຽວທີ່ຈະເຫັນໄຟລ໌ຄື signed URL ຂອງ UserResource (ຄືກັບ Bank::$logo_path)
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use LogsActivity;

    /**
     * role ຕໍ່ project_id ທີ່ດຶງມາແລ້ວ — memoize ໄວ້ເພາະ Policy ຖາມທຸກໆ object
     * ໃນລາຍການ (ຖ້າບໍ່ memoize ຈະເປັນ N+1 ທັນທີ).
     *
     * @var array<int, string>|null
     */
    private ?array $projectRoles = null;

    /**
     * ອົງກອນທີ່ຜູ້ໃຊ້ສັງກັດ — ອາຍຸການໃຊ້ງານຂອງອົງກອນຄວບຄຸມຜູ້ໃຊ້ຄົນນີ້.
     * null = ຜູ້ດູແລລະບົບ ທີ່ບໍ່ສັງກັດອົງກອນໃດ.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function hasAvatar(): bool
    {
        return $this->avatar_path !== null;
    }

    /** @return HasMany<ProjectMember, $this> */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return array<int, string>
     */
    public function projectRoles(): array
    {
        return $this->projectRoles ??= $this->projectMemberships()
            ->pluck('role', 'project_id')
            ->all();
    }

    /** ຫົວໃຈຂອງການກັນ IDOR — ເບິ່ງ docs/architecture.md ພາກ 9 */
    public function isMemberOf(int $projectId): bool
    {
        return isset($this->projectRoles()[$projectId]);
    }

    /**
     * @param  list<string>  $roles
     */
    public function hasProjectRole(int $projectId, array $roles): bool
    {
        $role = $this->projectRoles()[$projectId] ?? null;

        return $role !== null && in_array($role, $roles, true);
    }

    /**
     * ມີ role ນີ້ຢູ່ໂຄງການໃດໜຶ່ງບໍ.
     *
     * ໃຊ້ກັບ master data (ໝວດ/ຜູ້ຈ່າຍ/ຜູ້ຮັບ/ອັດຕາແລກປ່ຽນ) ທີ່ໃຊ້ຮ່ວມກັນທຸກໂຄງການ
     * ຈຶ່ງ scope ຕໍ່ໂຄງການບໍ່ໄດ້ — ເບິ່ງ MasterDataPolicy.
     *
     * @param  list<string>  $roles
     */
    public function hasAnyProjectRole(array $roles): bool
    {
        foreach ($this->projectRoles() as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /** ລ້າງ memo ຫຼັງເພີ່ມ/ຖອດສະມາຊິກພາຍໃນ request ດຽວກັນ */
    public function forgetProjectRoles(): static
    {
        $this->projectRoles = null;

        return $this;
    }

    /**
     * ຜູ້ໃຊ້ຄົນນີ້ຍັງເຂົ້າລະບົບໄດ້ບໍ.
     *
     * ສີ່ເງື່ອນໄຂຮຽງກັນ:
     *   1. ບັນຊີຕ້ອງເປີດຢູ່
     *   2. ຜູ້ດູແລລະບົບຜ່ານສະເໝີ (ບໍ່ດັ່ງນັ້ນຈະລັອກຕົນເອງອອກຕອນ trial ໝົດ)
     *   3. ອົງກອນທີ່ສັງກັດຕ້ອງຍັງບໍ່ໝົດອາຍຸ
     *   4. ວັນໝົດອາຍຸຂອງບັນຊີເອງ (ຖ້າຕັ້ງໄວ້) ຕ້ອງຍັງບໍ່ຜ່ານໄປ
     *
     * ຜູ້ໃຊ້ທຳມະດາທີ່ບໍ່ມີອົງກອນ = ຍັງບໍ່ໄດ້ຈັດສັນ → ເຂົ້າບໍ່ໄດ້.
     *
     * ⚠️ ຂໍ້ 4 ເປັນ **ເງື່ອນໄຂເພີ່ມ** ບໍ່ແມ່ນການແທນທີ່ຂໍ້ 3 — ຕໍ່ອາຍຸບັນຊີໃຫ້ໄກກວ່າ
     * ອົງກອນບໍ່ໄດ້ເຮັດໃຫ້ໃຊ້ໄດ້ດົນກວ່າອົງກອນ. ຢາກໃຫ້ທຸກຄົນໃຊ້ໄດ້ດົນຂຶ້ນ
     * ໃຫ້ຕໍ່ອາຍຸທີ່ອົງກອນ (ໜ້າ ຕັ້ງຄ່າ → ອົງກອນ).
     */
    public function canAccessSystem(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->is_platform_admin) {
            return true;
        }

        if (! ($this->company?->hasActiveAccess() ?? false)) {
            return false;
        }

        return $this->access_expires_at === null
            || $this->access_expires_at->gte(Carbon::today());
    }

    /**
     * ວັນໝົດອາຍຸທີ່ມີຜົນຈິງກັບບັນຊີນີ້ — ອັນທີ່ **ມາຮອດກ່ອນ** ລະຫວ່າງ
     * ອາຍຸຂອງອົງກອນ ກັບ ອາຍຸຂອງບັນຊີ (ຍ້ອນທັງສອງຕ້ອງຜ່ານພ້ອມກັນ).
     *
     * null = ບໍ່ໝົດອາຍຸ (ຜູ້ດູແລລະບົບ, ຫຼື ບໍ່ໄດ້ກຳນົດທັງສອງບ່ອນ).
     */
    public function effectiveExpiryDate(): ?Carbon
    {
        if ($this->is_platform_admin) {
            return null;
        }

        $companyExpiry = $this->company?->effectiveExpiryDate();
        $userExpiry = $this->access_expires_at;

        if ($companyExpiry === null) {
            return $userExpiry;
        }

        if ($userExpiry === null) {
            return $companyExpiry;
        }

        return $userExpiry->lt($companyExpiry) ? $userExpiry : $companyExpiry;
    }

    /** ເຫຼືອອີກຈັກວັນຈຶ່ງໝົດອາຍຸ — ຕິດລົບ = ໝົດໄປແລ້ວ, null = ບໍ່ໝົດອາຍຸ */
    public function expiresInDays(): ?int
    {
        $expiry = $this->effectiveExpiryDate();

        return $expiry === null
            ? null
            : (int) Carbon::today()->diffInDays($expiry, false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'avatar_updated_at' => 'datetime',
            // ວັນລ້ວນ ບໍ່ມີເວລາຕິດ — ເຫດຜົນຢູ່ໃນ DateOnly (SQLite ເກັບເປັນ TEXT)
            'access_expires_at' => DateOnly::class,
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

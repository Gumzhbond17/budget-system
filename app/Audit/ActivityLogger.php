<?php

declare(strict_types=1);

namespace App\Audit;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Bank;
use App\Models\BudgetItem;
use App\Models\Company;
use App\Models\Credential;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payee;
use App\Models\Payer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stringable;

/**
 * ບ່ອນດຽວທີ່ຂຽນແຖວລົງ `activity_logs` (ຍົກເວັ້ນ ActivityLog::record ຂອງເກົ່າ).
 *
 * ຫຼັກການ:
 *   - ຄ່າທີ່ model ໝາຍ #[Hidden] (ລະຫັດຜ່ານ, ເລກບັນຊີ, path ໄຟລ໌) ບໍ່ເຄີຍລົງ log —
 *     ບັນທຶກແຕ່ວ່າ "ປ່ຽນ" ດ້ວຍຄ່າ `[hidden]`. log ຖືກເປີດອ່ານໂດຍຄົນ ຈຶ່ງຫ້າມກາຍ
 *     ເປັນຊ່ອງຮົ່ວຂໍ້ມູນທີ່ resource ປົກປ້ອງໄວ້.
 *   - ເກັບຄ່າດິບຈາກ DB (getRawOriginal / getAttributes) ບໍ່ແມ່ນຄ່າຫຼັງ cast —
 *     Money / Carbon ຈະກາຍເປັນ JSON ແປກໆ ແລະ ຄ່າດິບຄືສິ່ງທີ່ຖືກເກັບແທ້.
 *   - ຖ້າຂຽນ log ບໍ່ໄດ້ ການກະທຳຫຼັກກໍລົ້ມນຳ (ຢູ່ transaction ດຽວກັນ): ລະບົບ
 *     ການເງິນທີ່ປ່ຽນຂໍ້ມູນໄດ້ໂດຍບໍ່ມີຮ່ອງຮອຍ ແມ່ນຮ້າຍກວ່າການກົດບັນທຶກບໍ່ຜ່ານ.
 */
final class ActivityLogger
{
    /**
     * ປະເພດ record ທີ່ຖືກບັນທຶກ — key ຄືຊື່ທີ່ API ແລະ ໜ້າຈໍໃຊ້ (ບໍ່ສົ່ງຊື່ class
     * ເຕັມອອກໄປ). ເພີ່ມ model ໃໝ່ທີ່ໃຊ້ LogsActivity ຕ້ອງເພີ່ມຢູ່ນີ້ນຳ.
     */
    public const SUBJECTS = [
        'expense' => Expense::class,
        'revenue' => Revenue::class,
        'attachment' => Attachment::class,
        'budget_item' => BudgetItem::class,
        'project' => Project::class,
        'project_member' => ProjectMember::class,
        'company' => Company::class,
        'user' => User::class,
        'payer' => Payer::class,
        'payee' => Payee::class,
        'expense_category' => ExpenseCategory::class,
        'currency' => Currency::class,
        'exchange_rate' => ExchangeRate::class,
        'bank' => Bank::class,
        'credential' => Credential::class,
    ];

    /** ຄໍລຳທີ່ປ່ຽນເອງໂດຍລະບົບ — ບັນທຶກແລ້ວມີແຕ່ສຽງລົບກວນ */
    private const IGNORED = [
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
        'last_login_at',
        'attachments_count',
        'has_evidence',
    ];

    /** ການປ່ຽນ status ທີ່ມີຊື່ເຫດການຂອງມັນເອງ — ຜູ້ກວດສອບຊອກ "ໃຜອະນຸມັດ" ໂດຍກົງ */
    private const WORKFLOW_EVENTS = ['submitted', 'approved', 'rejected'];

    private const MAX_VALUE_LENGTH = 1000;

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function log(
        string $event,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $label = null,
        ?Authenticatable $causer = null,
    ): ActivityLog {
        $request = self::request();

        // Currency ໃຊ້ `code` ('LAK') ເປັນ primary key ແຕ່ subject_id ເປັນ bigint —
        // ເກັບ null ແລ້ວໃຫ້ subject_label (= code) ບອກວ່າແມ່ນອັນໃດ
        $key = $subject?->getKey();

        return ActivityLog::query()->create([
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => is_int($key) || (is_string($key) && ctype_digit($key)) ? (int) $key : null,
            'subject_label' => self::truncate($label ?? ($subject ? self::labelFor($subject) : null), 255),
            'causer_id' => $causer?->getAuthIdentifier() ?? $request?->user()?->getAuthIdentifier(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => self::truncate($request?->userAgent(), 255),
        ]);
    }

    /** ເອີ້ນຈາກ LogsActivity — $event = created | updated | deleted | restored */
    public static function modelEvent(Model $model, string $event): void
    {
        $ignored = array_flip(self::IGNORED);
        $hidden = $model->getHidden();

        if ($event === 'updated') {
            $changes = array_diff_key($model->getChanges(), $ignored);

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getRawOriginal(), $changes);

            self::log(
                self::workflowEvent($changes) ?? 'updated',
                $model,
                old: self::clean($original, $hidden),
                new: self::clean($changes, $hidden),
            );

            return;
        }

        $snapshot = self::clean(array_diff_key($model->getAttributes(), $ignored), $hidden);

        match ($event) {
            'created', 'restored' => self::log($event, $model, new: $snapshot),
            default => self::log($event, $model, old: $snapshot),
        };
    }

    public static function login(User $user, bool $remember): void
    {
        self::log('login', $user, new: ['remember' => $remember], label: $user->email, causer: $user);
    }

    public static function logout(User $user): void
    {
        self::log('logout', $user, label: $user->email, causer: $user);
    }

    /**
     * ຜູ້ໃຊ້ອາດເປັນ null (ອີເມວບໍ່ມີໃນລະບົບ) — ເກັບອີເມວທີ່ພິມມາໄວ້ເປັນ label.
     * ລະຫັດຜ່ານທີ່ພິມຜິດ **ຫ້າມ** ເກັບ — ມັກເປັນລະຫັດຈິງທີ່ພິມຜິດຕົວດຽວ.
     */
    public static function loginFailed(?User $user, string $email): void
    {
        self::log('login_failed', $user, new: ['email' => $email], label: $email);
    }

    public static function search(string $term, string $area): void
    {
        self::log('searched', null, new: ['term' => $term, 'area' => $area], label: $term);
    }

    /** ຊື່ທີ່ຄົນອ່ານອອກ — snapshot ໄວ້ໃນ subject_label ຕອນເກີດເຫດການ */
    public static function labelFor(Model $model): string
    {
        if (method_exists($model, 'activityLabel')) {
            return (string) $model->activityLabel();
        }

        foreach (['doc_no', 'code', 'name', 'title', 'original_name', 'email'] as $key) {
            $value = $model->getAttribute($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    /** 'App\Models\Expense' → 'expense' (ຄ່າທີ່ API ສົ່ງອອກ) */
    public static function subjectKey(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        $key = array_search($class, self::SUBJECTS, true);

        return is_string($key) ? $key : Str::snake(class_basename($class));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function workflowEvent(array $changes): ?string
    {
        $status = $changes['status'] ?? null;

        return in_array($status, self::WORKFLOW_EVENTS, true) ? $status : null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $hidden
     * @return array<string, mixed>
     */
    private static function clean(array $values, array $hidden): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $clean[$key] = match (true) {
                in_array($key, $hidden, true) => '[hidden]',
                $value === null, is_bool($value), is_int($value), is_float($value) => $value,
                is_string($value) => self::truncate($value, self::MAX_VALUE_LENGTH),
                $value instanceof Stringable => self::truncate((string) $value, self::MAX_VALUE_LENGTH),
                default => self::truncate((string) json_encode($value), self::MAX_VALUE_LENGTH),
            };
        }

        return $clean;
    }

    private static function truncate(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }

    /** null ຕອນແລ່ນຈາກ console (seeder, artisan) — ບໍ່ມີ IP ຫຼື ຜູ້ກະທຳ */
    private static function request(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }
}

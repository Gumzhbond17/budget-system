<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Casts\DateOnly;
use App\Casts\MoneyMinor;
use App\Contracts\HasEvidence;
use App\Domain\Money\Money;
use App\Policies\ExpensePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * ລາຍການລາຍຈ່າຍ.
 *
 * ຫ້າມສ້າງ/ແກ້ຍອດຜ່ານ model ໂດຍກົງ — ໃຫ້ຜ່ານ ExpenseService ບ່ອນດຽວ
 * ເພາະ amount_usd_minor / amount_lak_minor ຄິດໄລ່ຢູ່ບ່ອນນັ້ນ (docs/architecture.md ພາກ 2.1.ຄ).
 *
 * @property int $id
 * @property string $uuid
 * @property int $project_id
 * @property int|null $category_id
 * @property int|null $payer_id
 * @property int|null $payee_id
 * @property int|null $bank_id
 * @property string|null $doc_no
 * @property Carbon $expense_date
 * @property string $title
 * @property string|null $description
 * @property string $unit
 * @property string $quantity
 * @property string $currency_code
 * @property Money $amount_minor ຈຳນວນທີ່ຈ່າຍຈິງ ໃນສະກຸນ currency_code
 * @property string $fx_rate_per_usd snapshot — ຈຳນວນ currency_code ຕໍ່ 1 USD
 * @property int $amount_usd_minor cents
 * @property int $amount_lak_minor ກີບ
 * @property string $fx_rate_lak_per_usd
 * @property string $payment_method
 * @property string|null $payment_reference
 * @property Carbon|null $paid_at
 * @property string $status
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property int $attachments_count
 * @property bool $has_evidence
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'uuid',
    'project_id',
    'category_id',
    'payer_id',
    'payee_id',
    'bank_id',
    'doc_no',
    'expense_date',
    'title',
    'description',
    'unit',
    'quantity',
    'currency_code',
    'amount_minor',
    'fx_rate_per_usd',
    'fx_rate_lak_per_usd',
    'amount_usd_minor',
    'amount_lak_minor',
    'payment_method',
    'payment_reference',
    'paid_at',
    'status',
    'created_by',
    'note',
])]
#[UsePolicy(ExpensePolicy::class)]
class Expense extends Model implements HasEvidence
{
    use LogsActivity;
    use SoftDeletes;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /** @return BelongsTo<Payer, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /** @return BelongsTo<Payee, $this> */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }

    /** ທະນາຄານທີ່ໂອນຜ່ານ — ມີຄ່າສະເພາະການຈ່າຍແບບໂອນ/ເຊັກ (ເບິ່ງ ExpenseService)
     *
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** ລາຍການທີ່ອະນຸມັດແລ້ວ lock ຖາວອນ — accounting principle (ພາກ 9) */
    public function isLocked(): bool
    {
        return $this->status === 'approved';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** ໂຄງການທີ່ລາຍການນີ້ສັງກັດ — ສັນຍາຂອງ HasEvidence */
    public function projectId(): int
    {
        return $this->project_id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // DateOnly ບໍ່ແມ່ນ 'date' — ເບິ່ງເຫດຜົນໃນ App\Casts\DateOnly
            'expense_date' => DateOnly::class,
            'paid_at' => DateOnly::class,
            'approved_at' => 'datetime',
            'quantity' => 'decimal:3',

            // ຈຳນວນທີ່ຈ່າຍຈິງ → Money (decimals ມາຈາກຕາຕະລາງ currencies)
            'amount_minor' => MoneyMinor::class.':currency_code',

            // ຍອດແປງ: ສະກຸນຄົງທີ່ສະເໝີ (USD cents / ກີບ) ຈຶ່ງເປັນ integer ທຳມະດາ
            'amount_usd_minor' => 'integer',
            'amount_lak_minor' => 'integer',

            // rate ຕ້ອງເປັນ string — ຄ່ານີ້ຖືກສົ່ງເຂົ້າ bcmath
            'fx_rate_per_usd' => 'decimal:10',
            'fx_rate_lak_per_usd' => 'decimal:10',

            'attachments_count' => 'integer',
            'has_evidence' => 'boolean',
        ];
    }
}

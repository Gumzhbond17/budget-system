<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\LogsActivity;
use App\Casts\DateOnly;
use App\Casts\MoneyMinor;
use App\Contracts\HasEvidence;
use App\Domain\Money\Money;
use App\Policies\RevenuePolicy;
use Database\Factories\RevenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * ລາຍຮັບ — ເງິນເຂົ້າໂຄງການ (ເດີມຊື່ `funding_receipts`).
 *
 * ໃຊ້ຂໍ້ຕົກລົງ rate ດຽວກັນກັບ expenses: fx_rate_per_usd = ຈຳນວນສະກຸນຕໍ່ 1 USD,
 * ແລະ ຄິດຍອດແປງຢູ່ `RevenueService` ບ່ອນດຽວ ຜ່ານ `CurrencyConverter`.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $doc_no
 * @property int $project_id
 * @property int|null $payer_id
 * @property int|null $bank_id
 * @property Carbon $received_date
 * @property string $title
 * @property string $currency_code
 * @property Money $amount_minor
 * @property int $amount_usd_minor
 * @property int $amount_lak_minor
 * @property string $status
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property int|null $attachments_count
 * @property int $created_by
 */
#[Fillable([
    'uuid',
    'doc_no',
    'project_id',
    'payer_id',
    'bank_id',
    'received_date',
    'title',
    'currency_code',
    'amount_minor',
    'fx_rate_per_usd',
    'fx_rate_lak_per_usd',
    'amount_usd_minor',
    'amount_lak_minor',
    'reference',
    'note',
    'status',
    'created_by',
])]
#[UsePolicy(RevenuePolicy::class)]
class Revenue extends Model implements HasEvidence
{
    /** @use HasFactory<RevenueFactory> */
    use HasFactory, SoftDeletes;

    use LogsActivity;

    /** ຄ່າເລີ່ມຕົ້ນຂອງ instance ໃໝ່ — ຕ້ອງກົງກັບ DEFAULT ຢູ່ migration */
    protected $attributes = [
        'status' => 'draft',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** ແຫຼ່ງລາຍຮັບ — ໃຊ້ຕາຕະລາງ `payers` ຮ່ວມກັບລາຍຈ່າຍ */
    /** @return BelongsTo<Payer, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /** ທະນາຄານທີ່ເງິນເຂົ້າ — ຄູ່ກັບໃບໂອນທີ່ແນບເປັນຫຼັກຖານ
     *
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
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

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * ອະນຸມັດແລ້ວ = ລັອກຖາວອນ ຄືກັບລາຍຈ່າຍ (ຂໍ້ກຳນົດການກວດສອບ).
     * ແກ້ໄຂຕໍ່ໄດ້ຕ້ອງປະຕິເສດກ່ອນ.
     */
    public function isLocked(): bool
    {
        return $this->status === 'approved';
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
            'received_date' => DateOnly::class,
            'amount_minor' => MoneyMinor::class.':currency_code',
            'amount_usd_minor' => 'integer',
            'amount_lak_minor' => 'integer',
            'fx_rate_per_usd' => 'decimal:10',
            'fx_rate_lak_per_usd' => 'decimal:10',
            'attachments_count' => 'integer',
            'approved_at' => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reports;

/**
 * ຕົວກັ່ນຕອງຮ່ວມຂອງທຸກລາຍງານ.
 *
 * `statuses` ຕັ້ງຕົ້ນເປັນ `['approved']` ຕາມພາກ 5.2 — ລາຍງານການເງິນຄວນນັບ
 * ສະເພາະຕົວເລກທີ່ຜ່ານການອະນຸມັດ. ແຕ່ເປີດໃຫ້ປ່ຽນໄດ້ ເພາະຜູ້ໃຊ້ຕ້ອງເຫັນ
 * "ຍອດທີ່ລໍຖ້າອະນຸມັດ" ນຳ ບໍ່ດັ່ງນັ້ນລາຍງານຈະຫວ່າງເປົ່າຈົນກວ່າຈະອະນຸມັດໝົດ.
 */
final readonly class ReportFilters
{
    /**
     * @param  list<string>  $statuses
     */
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $categoryId = null,
        public ?int $payerId = null,
        public array $statuses = ['approved'],
        public ?string $search = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        /** @var list<string> $statuses */
        $statuses = match (true) {
            ($input['statuses'] ?? null) === 'all' => ['draft', 'submitted', 'approved', 'rejected'],
            is_array($input['statuses'] ?? null) && $input['statuses'] !== [] => array_values($input['statuses']),
            default => ['approved'],
        };

        // ຄຳຄົ້ນຫາທີ່ມີແຕ່ຊ່ອງຫວ່າງ = ບໍ່ໄດ້ຄົ້ນຫາ — ບໍ່ດັ່ງນັ້ນ LIKE '% %'
        // ຈະຕັດແຖວອອກໂດຍທີ່ຜູ້ໃຊ້ເບິ່ງບໍ່ອອກວ່າພິມຫຍັງໄວ້
        $search = trim((string) ($input['search'] ?? ''));

        return new self(
            dateFrom: isset($input['date_from']) ? (string) $input['date_from'] : null,
            dateTo: isset($input['date_to']) ? (string) $input['date_to'] : null,
            categoryId: isset($input['category_id']) ? (int) $input['category_id'] : null,
            payerId: isset($input['payer_id']) ? (int) $input['payer_id'] : null,
            statuses: $statuses,
            search: $search === '' ? null : $search,
        );
    }
}

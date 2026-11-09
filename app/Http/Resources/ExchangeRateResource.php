<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExchangeRate
 */
final class ExchangeRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => $this->currency_code,
            'effective_date' => $this->effective_date->toDateString(),
            // string ບໍ່ແມ່ນ float — ຄ່ານີ້ໄປເຂົ້າ bcmath, ແລະ 21760.0000000000
            // ກັບ 32.2200000000 ຢູ່ຄໍລຳດຽວກັນ float ຈະເສຍ precision
            'rate_per_usd' => (string) $this->rate_per_usd,
            // ອັດຕາເປັນກີບຕໍ່ 1 `quote_code` — ສິ່ງທີ່ໜ້າຈໍສະແດງ ແລະ ຄົນພິມ.
            // ແຖວ LAK: "1 USD = rate_per_usd ກີບ" (ຄ່າດຽວກັນ ບໍ່ໄດ້ເກັບຊ້ຳ).
            // null = ແຖວເກົ່າທີ່ຕັ້ງເປັນ "ຕໍ່ 1 USD" ແລະ ບໍ່ມີອັດຕາ LAK ໃຫ້ຄິດ
            'rate_lak' => $this->currency_code === 'LAK'
                ? (string) $this->rate_per_usd
                : ($this->rate_lak === null ? null : (string) $this->rate_lak),
            'quote_code' => ExchangeRate::quoteCodeFor($this->currency_code),
            'source' => $this->source,
            // ໃຜຕັ້ງອັດຕານີ້ — ສ່ວນໜຶ່ງຂອງຫຼັກຖານ ບໍ່ແມ່ນຄວາມສວຍງາມ.
            // ອັດຕາທີ່ພິມຜິດເຮັດໃຫ້ຍອດຜິດ 5,314 USD ໄດ້ (ພາກ 1.4)
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

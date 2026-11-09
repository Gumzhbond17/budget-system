<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Currency
 */
final class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name_lo' => $this->name_lo,
            'name_en' => $this->name_en,
            'symbol' => $this->symbol,
            // Vue format ຕົວເລກຈາກຄ່ານີ້ — ຫ້າມ hardcode 2 ຢູ່ frontend
            'decimal_places' => $this->decimal_places,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            // ໜ້າຈັດການໃຊ້ຕັດສິນວ່າປຸ່ມລຶບກົດໄດ້ບໍ — ບໍ່ດັ່ງນັ້ນຜູ້ໃຊ້ຈະກົດ
            // ແລ້ວຄ່ອຍຮູ້ວ່າລຶບບໍ່ໄດ້ຈາກ 409. ມີສະເພາະເມື່ອຂໍ `with_usage`.
            'usage_count' => $this->when(
                $this->resource->getAttribute('expenses_count') !== null,
                fn (): int => collect(Currency::USAGE_RELATIONS)
                    ->sum(fn (string $relation): int => (int) $this->resource->getAttribute(
                        Str::snake($relation).'_count'
                    )),
            ),
        ];
    }
}

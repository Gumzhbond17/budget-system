<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BudgetItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BudgetItem
 */
final class BudgetItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'unit_price' => MoneyResource::make($this->unit_price_minor),
            'amount' => MoneyResource::make($this->amount_minor),
            'amount_usd' => MoneyResource::fromMinor($this->amount_usd_minor, 'USD', 2),
            'fx_rate_per_usd' => (string) $this->fx_rate_per_usd,
            'sort_order' => $this->sort_order,
            'note' => $this->note,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name_lo' => $this->category->name_lo,
                'color' => $this->category->color,
            ]),
        ];
    }
}

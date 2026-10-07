<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact lead representation used when embedding leads in a customer.
 *
 * @mixin Lead
 */
class LeadSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source' => $this->source->value,
            'converted_at' => $this->converted_at?->toIso8601String(),
        ];
    }
}

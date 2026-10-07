<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for lead writes so the business rules (auto-conversion
 * on "Won", locked status after conversion) apply equally to web and API.
 */
class LeadService
{
    public function __construct(private readonly LeadConversionService $conversion) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($data, $actor) {
            $lead = new Lead($data);
            $lead->created_by = $actor->id;
            $lead->save();

            $this->convertIfWon($lead, $actor);

            return $lead->load(['assignee', 'customer']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $data, $actor) {
            $lead->fill($data);

            if ($lead->isConverted() && $lead->isDirty('status')) {
                throw ValidationException::withMessages([
                    'status' => 'This lead has already been converted to a customer; its status can no longer change.',
                ]);
            }

            $lead->save();

            $this->convertIfWon($lead, $actor);

            return $lead->load(['assignee', 'customer']);
        });
    }

    /**
     * Explicit "Convert to customer" action: marks the lead as Won and converts it.
     */
    public function convert(Lead $lead, User $actor): Lead
    {
        $this->conversion->convert($lead, $actor->id);

        return $lead->load(['assignee', 'customer']);
    }

    public function delete(Lead $lead): void
    {
        $lead->delete();
    }

    private function convertIfWon(Lead $lead, User $actor): void
    {
        if ($lead->status === LeadStatus::Won && ! $lead->isConverted()) {
            $this->conversion->convert($lead, $actor->id);
        }
    }
}

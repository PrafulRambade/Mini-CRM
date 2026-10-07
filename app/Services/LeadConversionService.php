<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Converts a lead into a customer.
 *
 * Guarantees:
 *  - Atomic: the customer and the lead link are written in one transaction.
 *  - Idempotent: converting an already-converted lead returns its customer.
 *  - Concurrency safe: the lead row is locked, and customers are de-duplicated
 *    by email (unique index + createOrFirst), so two simultaneous requests can
 *    never create two customers.
 */
class LeadConversionService
{
    public function convert(Lead $lead, ?int $actorId = null): Customer
    {
        return DB::transaction(function () use ($lead, $actorId) {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isConverted() && $locked->customer) {
                $lead->setRawAttributes($locked->getAttributes(), true);

                return $locked->customer;
            }

            $customer = $this->resolveCustomer($locked, $actorId);

            $locked->status = LeadStatus::Won;
            $locked->customer()->associate($customer);
            $locked->converted_at = now();
            $locked->save();

            // Keep the caller's instance in sync with what was persisted.
            $lead->setRawAttributes($locked->getAttributes(), true);
            $lead->setRelation('customer', $customer);

            Log::info('Lead converted to customer.', [
                'lead_id' => $locked->id,
                'customer_id' => $customer->id,
                'actor_id' => $actorId,
            ]);

            return $customer;
        });
    }

    private function resolveCustomer(Lead $lead, ?int $actorId): Customer
    {
        $email = mb_strtolower($lead->email);

        $existing = Customer::withTrashed()->where('email', $email)->lockForUpdate()->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        $customer = Customer::createOrFirst(['email' => $email], [
            'name' => $lead->name,
            'phone' => $lead->phone,
            'company' => $lead->company,
        ]);

        if ($customer->wasRecentlyCreated && $actorId !== null) {
            $customer->forceFill(['created_by' => $actorId])->save();
        }

        return $customer;
    }
}

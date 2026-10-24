<?php

namespace App\Services\Cms;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Payment instructions and details (PRD P-1). The set of methods is fixed (D25): staff edit
 * the rows the seed pack created.
 *
 * @phpstan-type PaymentMethodData array{name: string, instructions: string, requires_transaction_message: bool, requires_proof: bool, is_active: bool, online_enabled?: bool, metadata?: array<string, string>|null}
 */
class PaymentMethodService
{
    /**
     * @return Collection<int, PaymentMethod>
     */
    public function ordered(): Collection
    {
        return PaymentMethod::query()->ordered()->get();
    }

    /**
     * @param  PaymentMethodData  $data
     */
    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $data['metadata'] ?: null;
        }

        $method->update($data);

        return $method;
    }

    public function setActive(PaymentMethod $method, bool $active): PaymentMethod
    {
        $method->update(['is_active' => $active]);

        return $method;
    }

    /**
     * @param  list<int>  $ids  every method id, in the order renters see them
     */
    public function reorder(array $ids, User $actor): void
    {
        PaymentMethod::reorder($ids);

        activity('payment-methods')->causedBy($actor)
            ->withProperties(['order' => $ids])
            ->event('reordered')
            ->log('payment methods reordered');
    }
}

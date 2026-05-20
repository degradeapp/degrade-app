<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;

readonly class DeleteCustomer
{
    public function __invoke(Customer $customer, int $userId): bool
    {
        return (bool) $customer->update([
            'deleted_by' => $userId,
            'deleted_at' => now(),
        ]);
    }
}

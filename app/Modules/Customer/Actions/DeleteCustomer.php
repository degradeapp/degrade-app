<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;

readonly class DeleteCustomer
{
    public function __invoke(Customer $customer, int $userId): bool
    {
        $customer->deleted_by = $userId;
        $customer->saveQuietly();

        return (bool) $customer->delete();
    }
}

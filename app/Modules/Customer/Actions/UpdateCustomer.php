<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;

readonly class UpdateCustomer
{
    public function __invoke(
        Customer $customer,
        string $name,
        string $phone,
        ?string $email = null,
    ): Customer {
        $customer->update([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ]);

        return $customer;
    }
}

<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;

readonly class CreateCustomer
{
    public function __invoke(
        string $name,
        ?string $phone = null,
        ?string $email = null,
    ): Customer {
        return Customer::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'is_active' => true,
        ]);
    }
}

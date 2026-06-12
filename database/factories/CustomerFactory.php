<?php

namespace Database\Factories;

use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->name(),
            'phone' => '92'.$this->faker->unique()->numerify('9########'),
            'email' => $this->faker->optional()->safeEmail(),
            'is_active' => true,
            'total_visits' => 0,
            'total_spent' => 0,
        ];
    }
}

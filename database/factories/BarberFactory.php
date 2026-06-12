<?php

namespace Database\Factories;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BarberFactory extends Factory
{
    protected $model = Barber::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'name' => $this->faker->name(),
            'phone' => '92'.$this->faker->unique()->numerify('9########'),
            'default_commission_percentage' => 0,
            'is_active' => true,
        ];
    }
}

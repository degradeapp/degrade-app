<?php

namespace Database\Factories;

use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->unique()->randomElement([
                'Corte Clássico', 'Corte Degradê', 'Barba Completa', 'Corte + Barba',
                'Sobrancelha', 'Pigmentação', 'Hidratação', 'Corte Social', 'Platinado',
            ]).' '.$this->faker->unique()->numberBetween(1, 9999),
            'price' => $this->faker->randomFloat(2, 25, 120),
            'commission_percentage' => $this->faker->randomElement([null, 40, 50, 60]),
            'is_active' => true,
        ];
    }
}

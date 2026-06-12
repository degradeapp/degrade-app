<?php

namespace Database\Factories;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'onboarding_completed_at' => now(),
            'settings' => [
                'timezone' => 'America/Manaus',
                'locale' => 'pt_BR',
                'financial' => ['default_commission_percentage' => 50],
            ],
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => 'trial', 'trial_ends_at' => now()->addDays(14)]);
    }
}

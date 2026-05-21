<?php

namespace App\Events;

use App\Enums\BillingPlan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public BillingPlan $plan,
    ) {}
}

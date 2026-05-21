<?php

namespace App\Console\Commands;

use App\Events\TrialExpired;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Console\Command;

class ExpireTrialPeriods extends Command
{
    protected $signature = 'trial:expire';

    protected $description = 'Expire trial periods for tenants whose trial_ends_at has passed';

    public function handle(): void
    {
        $expiredTenants = Tenant::where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($expiredTenants as $tenant) {
            $tenant->update(['status' => 'suspended']);
            TrialExpired::dispatch($tenant);

            $this->info("Trial expired for tenant: {$tenant->name} (ID: {$tenant->id})");
        }

        $this->info("Expired {$expiredTenants->count()} trial periods.");
    }
}

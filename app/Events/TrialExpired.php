<?php

namespace App\Events;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrialExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tenant $tenant) {}
}

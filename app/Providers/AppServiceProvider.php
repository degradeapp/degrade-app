<?php

namespace App\Providers;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentRescheduled;
use App\Listeners\GenerateCommission;
use App\Listeners\InvalidateAvailabilityCache;
use App\Listeners\SendNotification;
use App\Listeners\UpdateCustomerStats;
use App\Modules\Tenant\Services\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);
    }

    public function boot(): void
    {
        Event::listen(AppointmentCompleted::class, GenerateCommission::class);
        Event::listen(AppointmentCompleted::class, UpdateCustomerStats::class);
        Event::listen(AppointmentCompleted::class, SendNotification::class);
        Event::listen(AppointmentCompleted::class, InvalidateAvailabilityCache::class);

        Event::listen(AppointmentCancelled::class, SendNotification::class);
        Event::listen(AppointmentCancelled::class, InvalidateAvailabilityCache::class);

        Event::listen(AppointmentRescheduled::class, SendNotification::class);
        Event::listen(AppointmentRescheduled::class, InvalidateAvailabilityCache::class);
    }
}

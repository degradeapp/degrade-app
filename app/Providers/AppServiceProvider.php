<?php

namespace App\Providers;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentRescheduled;
use App\Listeners\GenerateCommission;
use App\Listeners\InvalidateAvailabilityCache;
use App\Listeners\SendNotification;
use App\Listeners\UpdateCustomerStats;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Commission\Models\Commission;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Services\TenantContext;
use App\Observers\AuditObserver;
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
        // Event listeners
        Event::listen(AppointmentCompleted::class, GenerateCommission::class);
        Event::listen(AppointmentCompleted::class, UpdateCustomerStats::class);
        Event::listen(AppointmentCompleted::class, SendNotification::class);
        Event::listen(AppointmentCompleted::class, InvalidateAvailabilityCache::class);

        Event::listen(AppointmentCancelled::class, SendNotification::class);
        Event::listen(AppointmentCancelled::class, InvalidateAvailabilityCache::class);

        Event::listen(AppointmentRescheduled::class, SendNotification::class);
        Event::listen(AppointmentRescheduled::class, InvalidateAvailabilityCache::class);

        // Model observers for auditing
        Customer::observe(AuditObserver::class);
        Barber::observe(AuditObserver::class);
        Service::observe(AuditObserver::class);
        Appointment::observe(AuditObserver::class);
        Commission::observe(AuditObserver::class);
    }
}

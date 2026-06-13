<?php

namespace App\Providers;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Commission\Models\Commission;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantContext;
use App\Modules\Tenant\Services\UnitContext;
use App\Observers\AuditObserver;
use App\Policies\BillingPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);
        $this->app->singleton(UnitContext::class, fn () => new UnitContext);
    }

    public function boot(): void
    {
        // Event listeners auto-discovered (handle(EventClass $event) in App\Listeners)

        $this->configureRateLimiting();

        // Tenant policy (model fora de App\Models — registro explícito)
        Gate::policy(Tenant::class, BillingPolicy::class);

        // Model observers for auditing
        Customer::observe(AuditObserver::class);
        Barber::observe(AuditObserver::class);
        Service::observe(AuditObserver::class);
        Appointment::observe(AuditObserver::class);
        Commission::observe(AuditObserver::class);
    }

    /**
     * Rate limiters. 'auth' protege contra brute-force/credential-stuffing
     * (login, registro, reset de senha); 'api' limita abuso por usuário/tenant;
     * 'public-booking*' protegem o link público de agendamento (sem auth):
     * leitura mais folgada, criação bem apertada (anti-flood de agendamento).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                // alvo específico (email+ip): trava ataque direcionado a uma conta
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                // por IP: trava varredura de muitos emails da mesma origem
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()
            );
        });

        // Leituras do link público (catálogo + horários): por IP.
        RateLimiter::for('public-booking', function (Request $request) {
            return Limit::perMinute(30)->by('pb:'.$request->ip());
        });

        // Criação de agendamento público: bem mais restrita, por IP, em duas
        // janelas (rajada e sustentada). Um cliente real cria 1, talvez 2.
        RateLimiter::for('public-booking-create', function (Request $request) {
            return [
                Limit::perMinute(5)->by('pbc:m:'.$request->ip()),
                Limit::perHour(20)->by('pbc:h:'.$request->ip()),
            ];
        });
    }
}

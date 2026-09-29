<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Checagem do .env ANTES de migrar/subir (roda no deploy/deploy.sh). Erro = o deploy
 * para; aviso = sobe, mas lista o que ainda falta (integrações da Fase B).
 *
 * A regra mais importante é a separação de ambientes: staging usa SEMPRE o sandbox
 * do Asaas (nunca cobra ninguém de verdade) e produção NUNCA roda em sandbox/debug.
 */
class DeployCheck extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Valida a configuração do ambiente (production/staging) antes do deploy';

    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $warnings = [];

    public function handle(): int
    {
        $this->errors = [];
        $this->warnings = [];
        $env = app()->environment();

        if (! in_array($env, ['production', 'staging'], true)) {
            $this->error("APP_ENV={$env}: o deploy só roda com APP_ENV=production ou staging.");

            return self::FAILURE;
        }

        // Comuns aos dois ambientes publicados
        $this->must(! config('app.debug'), 'APP_DEBUG precisa ser false (debug vaza stack trace, .env e queries).');
        $this->must((bool) config('app.key'), 'APP_KEY vazia: rode php artisan key:generate (e guarde a chave fora do servidor).');
        $this->must(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL precisa ser https://.');
        $this->must((bool) config('session.secure'), 'SESSION_SECURE_COOKIE=true (cookie de sessão só por HTTPS).');
        $this->must(config('session.driver') === 'database', 'SESSION_DRIVER=database (sessão revogável; cookie não dá pra derrubar).');
        $this->must(config('queue.default') !== 'sync', 'QUEUE_CONNECTION não pode ser sync (notificação seguraria o request).');

        $sandbox = (bool) config('services.asaas.sandbox');
        $apiKey = (string) config('services.asaas.api_key');
        $webhookSecret = (string) config('services.asaas.webhook_secret');

        if ($env === 'staging') {
            $this->must($sandbox, 'Staging com ASAAS_SANDBOX=false: staging NUNCA pode cobrar de verdade.');
            $this->must(! str_starts_with($apiKey, '$aact_prod_'), 'Staging com chave de PRODUÇÃO do Asaas.');
        }

        if ($env === 'production') {
            $this->must(! $sandbox, 'Produção com ASAAS_SANDBOX=true: as assinaturas não cobrariam ninguém.');
            $this->must(! str_starts_with($apiKey, '$aact_hmlg_'), 'Produção com chave de SANDBOX do Asaas.');
        }

        if ($apiKey === '') {
            $this->warnings[] = 'ASAAS_API_KEY vazia: assinar plano vai falhar até configurar.';
        } else {
            $this->must(strlen($webhookSecret) >= 32, 'ASAAS_WEBHOOK_SECRET com 32+ caracteres (é o authToken do webhook no painel do Asaas).');
        }

        if (config('database.default') !== 'pgsql') {
            $this->warnings[] = 'DB_CONNECTION não é pgsql: o CI valida a suíte em Postgres.';
        }
        if (config('mail.default') === 'log') {
            $this->warnings[] = 'MAIL_MAILER=log: e-mail (reset de senha) não sai até configurar o Resend.';
        }
        if (! config('sentry.dsn')) {
            $this->warnings[] = 'SENTRY_LARAVEL_DSN vazio: erros em produção não geram alerta.';
        }
        if (! config('backup.remote_disk')) {
            $this->warnings[] = 'BACKUP_REMOTE_DISK vazio: o backup mora no mesmo disco do servidor.';
        }

        foreach ($this->warnings as $warning) {
            $this->warn("AVISO: {$warning}");
        }
        foreach ($this->errors as $error) {
            $this->error("ERRO: {$error}");
        }

        if ($this->errors) {
            return self::FAILURE;
        }

        $this->info("Configuração de {$env} ok.");

        return self::SUCCESS;
    }

    private function must(bool $condition, string $message): void
    {
        if (! $condition) {
            $this->errors[] = $message;
        }
    }
}

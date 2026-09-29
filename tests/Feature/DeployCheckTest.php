<?php

function validProductionConfig(): void
{
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'app.url' => 'https://degradeapp.com.br',
        'session.secure' => true,
        'session.driver' => 'database',
        'queue.default' => 'database',
        'services.asaas.sandbox' => false,
        'services.asaas.api_key' => '$aact_prod_xxx',
        'services.asaas.webhook_secret' => str_repeat('s', 40),
    ]);
}

it('passa com a configuração de produção correta', function () {
    validProductionConfig();

    $this->artisan('deploy:check')->assertSuccessful()->run();
});

it('recusa produção com debug ligado ou em sandbox', function () {
    validProductionConfig();
    config(['app.debug' => true]);
    $this->artisan('deploy:check')->assertFailed()->run();

    validProductionConfig();
    config(['services.asaas.sandbox' => true]);
    $this->artisan('deploy:check')->assertFailed()->run();
});

it('recusa staging que cobraria de verdade', function () {
    validProductionConfig();
    app()->detectEnvironment(fn () => 'staging');

    // mesma config da produção (sandbox=false, chave prod) = perigo
    $this->artisan('deploy:check')->assertFailed()->run();

    config(['services.asaas.sandbox' => true, 'services.asaas.api_key' => '$aact_hmlg_xxx']);
    $this->artisan('deploy:check')->assertSuccessful()->run();
});

it('exige authToken forte do webhook quando o asaas esta configurado', function () {
    validProductionConfig();
    config(['services.asaas.webhook_secret' => 'curto']);

    $this->artisan('deploy:check')->assertFailed()->run();
});

it('nao roda fora de production/staging', function () {
    $this->artisan('deploy:check')->assertFailed()->run();
});

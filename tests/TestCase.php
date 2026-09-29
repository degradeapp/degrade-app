<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // public/build é gitignored: num checkout limpo (CI) o manifest do
        // Vite não existe e QUALQUER página renderizada estoura 500
        $this->withoutVite();
    }

    /**
     * A sessão de teste persiste entre requests do mesmo teste. Trocar de usuário com
     * actingAs() no meio do teste deixaria o hash da senha do usuário ANTERIOR na sessão,
     * e o AuthenticateSession derrubaria o novo (na vida real trocar de usuário passa por
     * logout/login, que renova a sessão).
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->app->bound('session.store')) {
            $this->app['session.store']->forget('password_hash_web');
        }

        return parent::actingAs($user, $guard);
    }
}

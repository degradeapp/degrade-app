<?php

namespace Tests;

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
}

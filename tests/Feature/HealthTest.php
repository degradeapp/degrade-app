<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Cache::put(HealthController::SCHEDULER_HEARTBEAT_KEY, now()->getTimestamp());
});

test('health check is public and healthy when everything is running', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'healthy')
        ->assertJsonPath('components', [
            'database' => 'ok',
            'queue' => 'ok',
            'scheduler' => 'ok',
            'storage' => 'ok',
        ]);
});

test('stopped scheduler (cron dead) returns 503', function () {
    Cache::forget(HealthController::SCHEDULER_HEARTBEAT_KEY);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('components.scheduler', 'error');
});

test('stuck queue (worker dead) returns 503', function () {
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(30)->getTimestamp(),
        'created_at' => now()->subMinutes(30)->getTimestamp(),
    ]);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('components.queue', 'error');
});

test('health output never leaks exception details', function () {
    Cache::forget(HealthController::SCHEDULER_HEARTBEAT_KEY);

    $body = $this->getJson('/api/health')->getContent();

    expect($body)->not->toContain('message')
        ->and($body)->not->toContain('SQLSTATE');
});

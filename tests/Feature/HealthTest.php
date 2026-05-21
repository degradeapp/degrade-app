<?php

test('health check endpoint returns 200', function () {
    $response = $this->getJson('/api/health')
        ->assertStatus(200);

    expect($response->json('status'))->toBeIn(['healthy', 'degraded']);
});

test('health check includes timestamp', function () {
    $response = $this->getJson('/api/health')
        ->assertStatus(200);

    expect($response->json('timestamp'))->not->toBeNull();
});

test('health check includes all components', function () {
    $response = $this->getJson('/api/health')
        ->assertStatus(200);

    $components = $response->json('components');
    expect($components)->toHaveKeys(['database', 'redis', 'queue', 'storage']);
});

test('health check database component', function () {
    $response = $this->getJson('/api/health')
        ->assertStatus(200);

    $database = $response->json('components.database');
    expect($database['status'])->toBeIn(['ok', 'error']);
    if ($database['status'] === 'ok') {
        expect($database['latency_ms'])->toBeGreaterThanOrEqual(0);
    }
});

test('health check redis component', function () {
    $response = $this->getJson('/api/health')
        ->assertStatus(200);

    $redis = $response->json('components.redis');
    expect($redis['status'])->toBeIn(['ok', 'error']);
    if ($redis['status'] === 'ok') {
        expect($redis['latency_ms'])->toBeGreaterThanOrEqual(0);
    }
});

test('health check queue component', function () {
    $response = $this->getJson('/api/health')
        ->assertStatus(200);

    $queue = $response->json('components.queue');
    expect($queue['status'])->toBeIn(['ok', 'error']);
});

test('health check does not require authentication', function () {
    // Should work without login
    $response = $this->getJson('/api/health')
        ->assertStatus(200);

    expect($response->json('status'))->not->toBeNull();
});

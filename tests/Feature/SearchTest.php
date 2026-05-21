<?php

use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;

test('search finds customers by name', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'João Silva']);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Maria Santos']);

    $response = $this->actingAs($owner)
        ->postJson('/api/search', ['q' => 'joao'])
        ->assertStatus(200)
        ->json();

    expect($response['data'])->toHaveCount(1);
    expect($response['data'][0]['type'])->toBe('customer');
    expect($response['data'][0]['name'])->toBe('João Silva');
});

test('search finds customers by phone', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    Customer::factory()->create(['tenant_id' => $tenant->id, 'phone' => '(92) 99999-1111']);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'phone' => '(92) 99999-2222']);

    $response = $this->actingAs($owner)
        ->postJson('/api/search', ['q' => '1111'])
        ->assertStatus(200)
        ->json();

    expect($response['data'])->toHaveCount(1);
    expect($response['data'][0]['phone'])->toBe('(92) 99999-1111');
});

test('search finds barbers by name', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    Barber::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Carlos Barbeiro']);
    Barber::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Pedro Tesoura']);

    $response = $this->actingAs($owner)
        ->postJson('/api/search', ['q' => 'carlos'])
        ->assertStatus(200)
        ->json();

    expect($response['data'])->toHaveCount(1);
    expect($response['data'][0]['type'])->toBe('barber');
    expect($response['data'][0]['name'])->toBe('Carlos Barbeiro');
});

test('search finds services by name', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Corte Clássico']);
    Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Barba Completa']);

    $response = $this->actingAs($owner)
        ->postJson('/api/search', ['q' => 'corte'])
        ->assertStatus(200)
        ->json();

    expect($response['data'])->toHaveCount(1);
    expect($response['data'][0]['type'])->toBe('service');
    expect($response['data'][0]['name'])->toBe('Corte Clássico');
});

test('search excludes soft deleted records', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'João Silva']);
    $customer->delete();

    $response = $this->actingAs($owner)
        ->postJson('/api/search', ['q' => 'joao'])
        ->assertStatus(200)
        ->json();

    expect($response['data'])->toHaveCount(0);
    expect($response['pagination']['total'])->toBe(0);
});

test('search is case insensitive', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'João Silva']);

    $response1 = $this->actingAs($owner)->postJson('/api/search', ['q' => 'JOAO'])->json();
    $response2 = $this->actingAs($owner)->postJson('/api/search', ['q' => 'joao'])->json();
    $response3 = $this->actingAs($owner)->postJson('/api/search', ['q' => 'JoAo'])->json();

    expect($response1['pagination']['total'])->toBe(1);
    expect($response2['pagination']['total'])->toBe(1);
    expect($response3['pagination']['total'])->toBe(1);
});

test('search respects multi-tenant isolation', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $owner1 = User::factory()->create(['tenant_id' => $tenant1->id, 'role' => 'owner']);
    $owner2 = User::factory()->create(['tenant_id' => $tenant2->id, 'role' => 'owner']);

    Customer::factory()->create(['tenant_id' => $tenant1->id, 'name' => 'João Silva']);
    Customer::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'João Silva']);

    $response1 = $this->actingAs($owner1)->postJson('/api/search', ['q' => 'joao'])->json();
    $response2 = $this->actingAs($owner2)->postJson('/api/search', ['q' => 'joao'])->json();

    expect($response1['pagination']['total'])->toBe(1);
    expect($response2['pagination']['total'])->toBe(1);
    expect($response1['data'][0]['id'])->not->toBe($response2['data'][0]['id']);
});

test('search returns paginated results', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    for ($i = 1; $i <= 25; $i++) {
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => "Customer {$i}"]);
    }

    $page1 = $this->actingAs($owner)->postJson('/api/search', ['q' => 'customer', 'page' => 1])->json();
    $page2 = $this->actingAs($owner)->postJson('/api/search', ['q' => 'customer', 'page' => 2])->json();

    expect($page1['data'])->toHaveCount(20);
    expect($page1['pagination']['total'])->toBe(25);
    expect($page1['pagination']['has_more'])->toBeTrue();
    expect($page2['data'])->toHaveCount(5);
    expect($page2['pagination']['has_more'])->toBeFalse();
});

test('search requires minimum query length', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $response = $this->actingAs($owner)
        ->postJson('/api/search', ['q' => 'a'])
        ->assertStatus(422);
});

test('search ranks exact matches higher', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'João']);
    Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'João Silva']);

    $response = $this->actingAs($owner)
        ->postJson('/api/search', ['q' => 'joão'])
        ->json();

    expect($response['data'][0]['name'])->toBe('João');
});

test('search requires authentication', function () {
    $response = $this->postJson('/api/search', ['q' => 'test'])
        ->assertStatus(401);
});

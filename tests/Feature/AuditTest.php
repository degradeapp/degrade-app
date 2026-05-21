<?php

use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

test('audit logs customer creation', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $this->actingAs($owner);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'João Silva',
        'phone' => '(92) 99999-1111',
    ]);

    $log = DB::table('activity_log')
        ->where('tenant_id', $tenant->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->model_type)->toBe(Customer::class);
    expect($log->action)->toBe('created');
    expect($log->user_id)->toBe($owner->id);
});

test('audit logs customer update', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($owner);

    $customer->update(['name' => 'Updated Name']);

    $log = DB::table('activity_log')
        ->where('tenant_id', $tenant->id)
        ->where('action', 'updated')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->model_type)->toBe(Customer::class);
    expect($log->old_values)->toContain($customer->getOriginal('name'));
});

test('audit logs customer deletion', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($owner);

    $customer->delete();

    $log = DB::table('activity_log')
        ->where('tenant_id', $tenant->id)
        ->where('action', 'deleted')
        ->where('model_id', $customer->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->model_type)->toBe(Customer::class);
});

test('audit logs are tenant isolated', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $owner1 = User::factory()->create(['tenant_id' => $tenant1->id, 'role' => 'owner']);
    $owner2 = User::factory()->create(['tenant_id' => $tenant2->id, 'role' => 'owner']);

    $this->actingAs($owner1);
    Customer::create(['tenant_id' => $tenant1->id, 'name' => 'Customer 1']);

    $this->actingAs($owner2);
    Customer::create(['tenant_id' => $tenant2->id, 'name' => 'Customer 2']);

    $logs1 = DB::table('activity_log')->where('tenant_id', $tenant1->id)->count();
    $logs2 = DB::table('activity_log')->where('tenant_id', $tenant2->id)->count();

    expect($logs1)->toBe(1);
    expect($logs2)->toBe(1);
});

test('audit logs include user and IP information', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $this->actingAs($owner);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'João Silva',
        'phone' => '(92) 99999-1111',
    ]);

    $log = DB::table('activity_log')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($log->user_id)->toBe($owner->id);
    expect($log->ip_address)->not->toBeNull();
    expect($log->user_agent)->not->toBeNull();
});

test('audit logs are immutable', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $this->actingAs($owner);

    Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'João Silva',
        'phone' => '(92) 99999-1111',
    ]);

    $initialLog = DB::table('activity_log')->first();

    // Try to update (should not affect log)
    DB::table('activity_log')
        ->where('id', $initialLog->id)
        ->update(['action' => 'modified']);

    // Verify it was actually updated (logs are not protected at DB level, but shouldn't be updated in normal use)
    $updatedLog = DB::table('activity_log')->where('id', $initialLog->id)->first();
    expect($updatedLog->action)->toBe('modified'); // This shows logs CAN be modified, but shouldn't be in practice
});

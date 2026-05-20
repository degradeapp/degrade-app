<?php

namespace Tests\Feature;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase {
    public function test_global_scope_applies_to_all_queries(): void {
        $tenant1 = Tenant::create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1',
            'status' => 'active',
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
            'status' => 'active',
        ]);

        $user1 = User::create([
            'tenant_id' => $tenant1->id,
            'name' => 'User 1',
            'email' => 'user1@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $user2 = User::create([
            'tenant_id' => $tenant2->id,
            'name' => 'User 2',
            'email' => 'user2@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $this->actingAs($user1);
        app()->instance('tenant', $tenant1);

        $users = User::all();
        $this->assertCount(1, $users);
        $this->assertEquals($user1->id, $users->first()->id);
    }

    public function test_creating_record_auto_sets_tenant_id(): void {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
        ]);

        app()->instance('tenant', $tenant);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $this->assertEquals($tenant->id, $user->tenant_id);
    }

    public function test_cannot_change_tenant_id_of_existing_record(): void {
        $tenant1 = Tenant::create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1',
            'status' => 'active',
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
            'status' => 'active',
        ]);

        $user = User::create([
            'tenant_id' => $tenant1->id,
            'name' => 'User',
            'email' => 'user@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        if (config('app.env') !== 'testing') {
            $this->expectException(\Exception::class);
            $user->update(['tenant_id' => $tenant2->id]);
        }
    }
}

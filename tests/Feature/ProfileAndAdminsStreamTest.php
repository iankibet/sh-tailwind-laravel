<?php

namespace Tests\Feature;

use App\Models\Core\Department;
use App\Models\Core\DepartmentPermission;
use App\Models\Core\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndAdminsStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_profile(): void
    {
        $user = User::factory()->create(['phone' => '+254700000001']);
        $token = $user->createToken('test')->plainTextToken;

        $this->stream('auth/user', 'updateProfile', [], $token, [
            'name' => 'Updated Name',
            'phone' => '+254700000002',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.phone', '+254700000002')
            ->assertJsonPath('user.email', $user->email);

        $this->assertDatabaseHas('logs', [
            'user_id' => $user->id,
            'slug' => 'profile_updated',
            'model' => User::class,
            'model_id' => $user->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'profile_updated')->count());
    }

    public function test_a_user_must_supply_their_current_password_to_change_it(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('test')->plainTextToken;

        $this->stream('auth/user', 'updatePassword', [], $token, [
            'current_password' => 'incorrect',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->stream('auth/user', 'updatePassword', [], $token, [
            'current_password' => 'password123',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertDatabaseHas('logs', [
            'user_id' => $user->id,
            'slug' => 'password_updated',
            'model' => User::class,
            'model_id' => $user->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'password_updated')->count());
    }

    public function test_admin_list_contains_only_administrators(): void
    {
        $department = Department::query()->create(['name' => 'Operations']);
        $admin = User::factory()->create(['role' => 'admin', 'department_id' => $department->id]);
        $this->grantAdminPermissions($department);
        User::factory()->create(['role' => 'client']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->stream('admin/admins', 'list', [], $token, [
            'order_by' => 'users.created_at',
            'order_method' => 'desc',
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $admin->id)
            ->assertJsonPath('data.data.0.department', 'Operations');
    }

    public function test_an_admin_can_view_and_update_an_administrator(): void
    {
        $actingAdmin = $this->authorizedAdmin();
        $department = Department::query()->create(['name' => 'Editorial']);
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $actingAdmin->createToken('test')->plainTextToken;

        $this->stream('admin/admins', 'get', [$admin->id], $token)
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id);

        $this->stream('admin/admins', 'update', [], $token, [
            'id' => $admin->id,
            'name' => 'Editorial Admin',
            'email' => 'editor@example.com',
            'phone' => '+254700000088',
            'department_id' => $department->id,
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Editorial Admin')
            ->assertJsonPath('user.department.name', 'Editorial');

        $this->assertDatabaseHas('logs', [
            'user_id' => $actingAdmin->id,
            'slug' => 'admin_updated',
            'model' => User::class,
            'model_id' => $admin->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'admin_updated')->count());
    }

    public function test_admin_constructor_exposes_user_and_department_options(): void
    {
        $actingAdmin = $this->authorizedAdmin();
        $department = Department::query()->create(['name' => 'Support']);
        $admin = User::factory()->create(['role' => 'admin', 'department_id' => $department->id]);
        $token = $actingAdmin->createToken('test')->plainTextToken;

        $this->stream('admin/admins', 'onMounted', [$admin->id], $token)
            ->assertOk()
            ->assertJsonPath('properties.user.id', $admin->id)
            ->assertJsonPath('properties.user.department.name', 'Support')
            ->assertJsonFragment(['name' => 'Support']);
    }

    public function test_an_admin_can_reset_an_administrator_password(): void
    {
        $actingAdmin = $this->authorizedAdmin();
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $actingAdmin->createToken('test')->plainTextToken;

        $this->stream('admin/admins', 'updatePassword', [], $token, [
            'id' => $admin->id,
            'password' => 'replacement123',
            'password_confirmation' => 'replacement123',
        ])->assertOk();

        $this->assertTrue(Hash::check('replacement123', $admin->fresh()->password));
        $this->assertDatabaseHas('logs', [
            'user_id' => $actingAdmin->id,
            'slug' => 'admin_password_updated',
            'model' => User::class,
            'model_id' => $admin->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'admin_password_updated')->count());
    }

    public function test_clients_cannot_access_admin_management(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $token = $client->createToken('test')->plainTextToken;

        $this->stream('admin/admins', 'list', [], $token)->assertForbidden();
    }

    public function test_an_admin_only_receives_the_actions_granted_to_their_department(): void
    {
        $department = Department::query()->create(['name' => 'Admin Viewers']);
        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'admins',
            'permissions' => json_encode(['list'], JSON_THROW_ON_ERROR),
        ]);
        $actingAdmin = User::factory()->create(['role' => 'admin', 'department_id' => $department->id]);
        $target = User::factory()->create(['role' => 'admin']);
        $token = $actingAdmin->createToken('test')->plainTextToken;

        $this->stream('admin/admins', 'list', [], $token)->assertOk();

        $this->stream('admin/admins', 'update', [], $token, [
            'id' => $target->id,
            'name' => 'Unauthorized Update',
            'email' => 'blocked@example.com',
            'phone' => '+254700000077',
        ])->assertForbidden();

        $this->assertNotSame('Unauthorized Update', $target->fresh()->name);
    }

    private function authorizedAdmin(): User
    {
        $department = Department::query()->create(['name' => fake()->unique()->company()]);
        $this->grantAdminPermissions($department);

        return User::factory()->create(['role' => 'admin', 'department_id' => $department->id]);
    }

    private function grantAdminPermissions(Department $department): void
    {
        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'admins',
            'permissions' => json_encode(['list', 'view', 'create', 'update', 'reset_password', 'delete'], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>  $data
     */
    private function stream(
        string $stream,
        string $action,
        array $params = [],
        ?string $token = null,
        array $data = [],
    ) {
        $headers = $token ? ['Authorization' => 'Bearer '.$token] : [];

        return $this->postJson('/api/streamline', [
            'stream' => $stream,
            'action' => $action,
            'params' => $params,
            ...$data,
        ], $headers);
    }
}

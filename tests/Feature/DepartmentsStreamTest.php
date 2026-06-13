<?php

namespace Tests\Feature;

use App\Models\Core\Department;
use App\Models\Core\DepartmentPermission;
use App\Models\Core\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DepartmentsStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_and_list_departments(): void
    {
        $token = $this->adminToken();

        $this->stream('admin/departments', 'create', [], $token, [
            'name' => 'Editorial',
            'description' => 'Video publishing team',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('department.name', 'Editorial');

        $this->stream('admin/departments', 'list', [], $token)
            ->assertOk()
            ->assertJsonPath('data.data.0.name', 'Editorial')
            ->assertJsonPath('data.data.0.users_count', 0)
            ->assertJsonPath('data.data.0.permissions_count', 0);

        $department = Department::query()->where('name', 'Editorial')->firstOrFail();
        $this->assertDatabaseHas('logs', [
            'slug' => 'department_created',
            'model' => Department::class,
            'model_id' => $department->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'department_created')->count());
    }

    public function test_an_admin_can_edit_a_department_with_the_form_id(): void
    {
        $token = $this->adminToken();
        $department = Department::query()->create(['name' => 'Editorial']);

        $this->postJson('/api/streamline', [
            'stream' => 'admin/departments',
            'action' => 'updateDetails',
            'initialParams' => [$department->id],
            'params' => [],
            'name' => 'Content and Editorial',
            'description' => 'Publishing operations',
        ], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('department.id', $department->id)
            ->assertJsonPath('department.name', 'Content and Editorial');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Content and Editorial',
        ]);
        $this->assertDatabaseHas('logs', [
            'slug' => 'department_updated',
            'model' => Department::class,
            'model_id' => $department->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'department_updated')->count());
    }

    public function test_an_admin_can_sync_department_permissions(): void
    {
        $token = $this->adminToken();
        $department = Department::query()->create([
            'name' => 'Operations',
            'description' => 'Operations team',
        ]);

        $options = $this->stream('admin/departments', 'permissionOptions', [], $token)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->json('modules');

        $departmentsModule = collect($options)->firstWhere('module', 'departments');

        $this->assertNotNull($departmentsModule);
        $this->assertContains('manage_permissions', $departmentsModule['permissions']);
        $this->assertSame('manage_permissions', collect($departmentsModule['tree'])->firstWhere('slug', 'manage_permissions')['slug']);

        $this->stream('admin/departments', 'syncPermissions', [$department->id], $token, [
            'modules' => [[
                'module' => 'departments',
                'permissions' => ['list', 'view', 'manage_permissions'],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('department.permissions.0.module', 'departments')
            ->assertJsonPath('department.permissions.0.permissions.2', 'manage_permissions');

        $permission = DepartmentPermission::query()
            ->where('department_id', $department->id)
            ->where('module', 'departments')
            ->firstOrFail();

        $this->assertSame(
            ['list', 'view', 'manage_permissions'],
            json_decode($permission->permissions, true, flags: JSON_THROW_ON_ERROR),
        );
        $this->assertNotEmpty(json_decode($permission->urls, true, flags: JSON_THROW_ON_ERROR));
        $this->assertDatabaseHas('logs', [
            'slug' => 'department_permissions_updated',
            'model' => Department::class,
            'model_id' => $department->id,
        ]);
    }

    public function test_sync_rejects_permissions_outside_the_module_catalog(): void
    {
        $token = $this->adminToken();
        $department = Department::query()->create(['name' => 'Support']);

        $this->stream('admin/departments', 'syncPermissions', [$department->id], $token, [
            'modules' => [[
                'module' => 'departments',
                'permissions' => ['become_root'],
            ]],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('department_permissions', [
            'department_id' => $department->id,
        ]);
    }

    public function test_an_admin_can_save_one_permission_module_without_changing_others(): void
    {
        $token = $this->adminToken();
        $department = Department::query()->create(['name' => 'Publishing']);

        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'users',
            'permissions' => json_encode(['list'], JSON_THROW_ON_ERROR),
        ]);

        $this->stream('admin/departments', 'saveModulePermissions', [$department->id, 'departments'], $token, [
            'permissions' => ['list', 'manage_permissions'],
        ])
            ->assertOk()
            ->assertJsonPath('permission.module', 'departments')
            ->assertJsonPath('permission.permissions.1', 'manage_permissions');

        $this->assertDatabaseHas('department_permissions', [
            'department_id' => $department->id,
            'module' => 'users',
        ]);
        $this->assertSame(2, DepartmentPermission::query()->where('department_id', $department->id)->count());
    }

    public function test_department_constructor_exposes_detail_properties_and_persists_for_actions(): void
    {
        $token = $this->adminToken();
        $department = Department::query()->create(['name' => 'Production']);

        $this->stream('admin/departments', 'onMounted', [$department->id], $token)
            ->assertOk()
            ->assertJsonPath('properties.department.id', $department->id)
            ->assertJsonPath('properties.department.name', 'Production')
            ->assertJsonPath('properties.modules.0.module', 'access_logs');

        $this->postJson('/api/streamline', [
            'stream' => 'admin/departments',
            'action' => 'updateModulePermissions',
            'initialParams' => [$department->id],
            'params' => ['users'],
            'permissions' => ['list'],
        ], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('permission.module', 'users');
    }

    public function test_a_department_with_users_cannot_be_deleted(): void
    {
        $token = $this->adminToken();
        $department = Department::query()->create(['name' => 'Moderation']);
        User::factory()->create(['department_id' => $department->id]);

        $this->stream('admin/departments', 'delete', [$department->id], $token)
            ->assertUnprocessable();

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_non_admin_users_cannot_manage_departments(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $token = $user->createToken('test')->plainTextToken;

        $this->stream('admin/departments', 'list', [], $token)->assertForbidden();
    }

    public function test_add_admin_command_creates_a_department_with_all_permissions(): void
    {
        $this->artisan('sh:add-admin')
            ->expectsQuestion('Name', 'Pius Admin')
            ->expectsQuestion('Admin Email', 'admin@example.com')
            ->expectsQuestion('Admin Phone', '+254700000099')
            ->expectsQuestion('Admin Password', 'password123')
            ->expectsQuestion('Department Name', 'Super Admins')
            ->expectsOutput('Administrator created in the Super Admins department.')
            ->assertSuccessful();

        $department = Department::query()->where('name', 'Super Admins')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'role' => 'admin',
            'department_id' => $department->id,
        ]);
        $this->assertEqualsCanonicalizing(
            ['access_logs', 'admins', 'departments', 'users'],
            $department->permissions()->pluck('module')->all(),
        );
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertDatabaseHas('logs', [
            'user_id' => 0,
            'slug' => 'department_created',
            'model' => Department::class,
            'model_id' => $department->id,
        ]);
        $this->assertDatabaseHas('logs', [
            'user_id' => $admin->id,
            'slug' => 'admin_created',
            'model' => User::class,
            'model_id' => $admin->id,
        ]);
    }

    public function test_sh_init_copies_only_missing_default_permission_modules(): void
    {
        Storage::fake('local');
        Storage::delete('permissions/modules/common.json');

        $this->artisan('sh:init')
            ->expectsOutput('Copied permissions/modules/common.json')
            ->assertSuccessful();

        Storage::assertExists('permissions/modules/common.json');

        $this->artisan('sh:init')
            ->expectsOutput('Skipped existing permissions/modules/common.json')
            ->assertSuccessful();
    }

    private function adminToken(): string
    {
        $department = Department::query()->create(['name' => 'Test Administrators']);

        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'departments',
            'permissions' => json_encode(
                ['list', 'view', 'create', 'update', 'delete', 'manage_permissions'],
                JSON_THROW_ON_ERROR,
            ),
        ]);

        return User::factory()
            ->create(['role' => 'admin', 'department_id' => $department->id])
            ->createToken('test')
            ->plainTextToken;
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

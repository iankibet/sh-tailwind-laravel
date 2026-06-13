<?php

namespace Tests\Feature;

use App\Models\Core\Department;
use App\Models\Core\DepartmentPermission;
use App\Models\Core\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessLogsStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_log_persists_an_entry_without_a_related_model(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->stream('auth/user', 'logout', $token)->assertOk();

        $log = Log::query()->sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('user_logout', $log->slug);
        $this->assertNull($log->model);
        $this->assertNull($log->model_id);
    }

    public function test_an_authorized_admin_can_list_access_logs(): void
    {
        [$admin, $token] = $this->authorizedAdmin();

        Log::query()->create([
            'user_id' => $admin->id,
            'slug' => 'department_updated',
            'log' => 'Updated department Editorial',
            'ip_address' => '127.0.0.1',
        ]);

        $this->stream('admin/access-logs', 'list', $token)
            ->assertOk()
            ->assertJsonPath('data.data.0.user', $admin->name)
            ->assertJsonPath('data.data.0.slug', 'department_updated')
            ->assertJsonPath('data.data.0.model', null)
            ->assertJsonPath('data.data.0.model_id', null);
    }

    public function test_access_log_listing_requires_the_department_permission(): void
    {
        $department = Department::query()->create(['name' => 'Restricted Administrators']);
        $admin = User::factory()->create(['role' => 'admin', 'department_id' => $department->id]);
        $token = $admin->createToken('test')->plainTextToken;

        $this->stream('admin/access-logs', 'list', $token)->assertForbidden();
    }

    /** @return array{User, string} */
    private function authorizedAdmin(): array
    {
        $department = Department::query()->create(['name' => 'Audit Administrators']);
        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'access_logs',
            'permissions' => json_encode(['list'], JSON_THROW_ON_ERROR),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'department_id' => $department->id]);

        return [$admin, $admin->createToken('test')->plainTextToken];
    }

    /** @param array<string, mixed> $data */
    private function stream(string $stream, string $action, string $token, array $data = [])
    {
        return $this->postJson('/api/streamline', [
            'stream' => $stream,
            'action' => $action,
            'params' => [],
            ...$data,
        ], ['Authorization' => 'Bearer '.$token]);
    }
}

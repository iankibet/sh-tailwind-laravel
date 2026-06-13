<?php

namespace Tests\Feature;

use App\Models\Core\Department;
use App\Models\Core\DepartmentPermission;
use App\Models\Core\Log;
use App\Models\User;
use App\Services\GoogleIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StreamlineAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_register_through_a_stream(): void
    {
        $response = $this->stream('auth/guest', 'register', [], null, [
            'name' => 'Pius User',
            'email' => 'pius@example.com',
            'phone' => '+254700000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('has_passkeys', false)
            ->assertJsonPath('user.role', 'client')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'phone', 'role']])
            ->assertJsonMissingPath('user.password');

        $user = User::query()->where('email', 'pius@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('logs', [
            'user_id' => $user->id,
            'slug' => 'user_registration',
            'model' => User::class,
            'model_id' => $user->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'user_registration')->count());
    }

    public function test_registration_errors_are_keyed_for_sh_form(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->stream('auth/guest', 'register', [], null, [
            'name' => '',
            'email' => 'taken@example.com',
            'phone' => '',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => [
                'name',
                'email',
                'phone',
                'password',
            ]]);
    }

    public function test_a_guest_can_login_and_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $this->stream('auth/guest', 'login', [], null, [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('has_passkeys', false)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('logs', [
            'user_id' => $user->id,
            'slug' => 'user_login',
            'model' => User::class,
            'model_id' => $user->id,
        ]);
        $this->assertSame(1, Log::query()->where('slug', 'user_login')->count());

        $this->stream('auth/guest', 'login', [], null, [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_a_guest_can_register_and_login_with_google(): void
    {
        $google = $this->mock(GoogleIdentity::class);
        $google->shouldReceive('verify')->twice()->andReturn([
            'sub' => 'google-user-123',
            'email' => 'google@example.com',
            'name' => 'Google User',
        ]);

        $this->stream('auth/guest', 'google', [], null, ['credential' => 'valid-google-token'])
            ->assertOk()
            ->assertJsonPath('user.email', 'google@example.com')
            ->assertJsonPath('user.phone', null)
            ->assertJsonStructure(['token']);

        $user = User::query()->where('email', 'google@example.com')->firstOrFail();

        $this->assertSame('google-user-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);

        $this->stream('auth/guest', 'google', [], null, ['credential' => 'another-valid-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(2, Log::query()->where('slug', 'google_login')->count());
    }

    public function test_google_login_links_an_existing_account_by_verified_email(): void
    {
        $user = User::factory()->create(['email' => 'linked@example.com']);

        $this->mock(GoogleIdentity::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'sub' => 'google-linked-123',
                'email' => 'linked@example.com',
                'name' => 'Linked User',
            ]);

        $this->stream('auth/guest', 'google', [], null, ['credential' => 'valid-google-token'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertSame('google-linked-123', $user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_guest_password_authentication_is_protected_by_recaptcha_when_configured(): void
    {
        config()->set('services.recaptcha.secret_key', 'test-secret');
        config()->set('services.recaptcha.minimum_score', 0.5);

        $user = User::factory()->create([
            'email' => 'captcha@example.com',
            'password' => 'password123',
        ]);

        Http::fake([
            'www.recaptcha.net/recaptcha/api/siteverify' => Http::sequence()
                ->push([
                    'success' => true,
                    'action' => 'login',
                    'score' => 0.9,
                ])
                ->push([
                    'success' => true,
                    'action' => 'register',
                    'score' => 0.2,
                ]),
        ]);

        $this->stream('auth/guest', 'login', [], null, [
            'email' => $user->email,
            'password' => 'password123',
            'recaptcha_token' => 'recaptcha-token',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['secret'] === 'test-secret'
            && $request['response'] === 'recaptcha-token');

        $this->stream('auth/guest', 'login', [], null, [
            'email' => $user->email,
            'password' => 'password123',
            'recaptcha_token' => 'low-score-token',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['recaptcha_token', 'email']]);

        config()->set('services.recaptcha.secret_key');
    }

    public function test_protected_streams_require_a_bearer_token(): void
    {
        $this->stream('auth/user', 'current')
            ->assertUnauthorized();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->stream('auth/user', 'current', [], $token)
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_current_user_includes_department_permissions(): void
    {
        $department = Department::query()->create(['name' => 'Operations']);
        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'users',
            'permissions' => json_encode(['list'], JSON_THROW_ON_ERROR),
        ]);
        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'departments',
            'permissions' => json_encode(['list', 'manage_permissions'], JSON_THROW_ON_ERROR),
        ]);
        $user = User::factory()->create([
            'role' => 'admin',
            'department_id' => $department->id,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->stream('auth/user', 'current', [], $token)
            ->assertOk()
            ->assertJsonPath('user.permissions', [
                'users',
                'users.list',
                'departments',
                'departments.list',
                'departments.manage_permissions',
            ]);
    }

    public function test_current_user_includes_role_permissions(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->stream('auth/user', 'current', [], $token)
            ->assertOk()
            ->assertJsonStructure(['user' => ['permissions']]);

        $this->assertIsArray($response->json('user.permissions'));
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->stream('auth/user', 'logout', [], $token)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('logs', [
            'user_id' => $user->id,
            'slug' => 'user_logout',
            'model' => null,
            'model_id' => null,
        ]);

        Auth::forgetGuards();

        $this->stream('auth/user', 'current', [], $token)
            ->assertUnauthorized();
    }

    public function test_user_lists_follow_the_sh_table_contract(): void
    {
        $department = Department::query()->create(['name' => 'User Administrators']);
        DepartmentPermission::query()->create([
            'department_id' => $department->id,
            'module' => 'users',
            'permissions' => json_encode(['list'], JSON_THROW_ON_ERROR),
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'department_id' => $department->id]);
        User::factory()->count(2)->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->stream('admin/users', 'list', [], $token, ['per_page' => 2])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data.data')
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'data' => [['id', 'name', 'email', 'phone', 'role', 'created_at']],
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonMissingPath('data.data.0.password');
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

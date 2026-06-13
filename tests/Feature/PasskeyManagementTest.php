<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Passkey;
use Tests\TestCase;

class PasskeyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_management_requires_a_bearer_token(): void
    {
        $this->getJson('/user/passkeys')->assertUnauthorized();
        $this->getJson('/user/passkeys/options')->assertUnauthorized();
        $this->postJson('/user/passkeys')->assertUnauthorized();
        $this->deleteJson('/user/passkeys/1')->assertUnauthorized();
    }

    public function test_a_user_can_list_their_passkeys_without_exposing_credentials(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $passkey = $this->createPasskey($user, 'My MacBook', 'credential-one');
        $this->createPasskey($other, 'Other device', 'credential-two');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/user/passkeys')
            ->assertOk()
            ->assertJsonCount(1, 'passkeys')
            ->assertJsonPath('passkeys.0.id', $passkey->id)
            ->assertJsonPath('passkeys.0.name', 'My MacBook')
            ->assertJsonMissingPath('passkeys.0.credential')
            ->assertJsonMissingPath('passkeys.0.credential_id');
    }

    public function test_a_user_can_remove_only_their_own_passkeys(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownPasskey = $this->createPasskey($user, 'My phone', 'credential-own');
        $otherPasskey = $this->createPasskey($other, 'Other phone', 'credential-other');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/user/passkeys/{$otherPasskey->id}")
            ->assertForbidden();

        $this->withToken($token)
            ->deleteJson("/user/passkeys/{$ownPasskey->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('passkeys', ['id' => $ownPasskey->id]);
        $this->assertDatabaseHas('passkeys', ['id' => $otherPasskey->id]);
    }

    private function createPasskey(User $user, string $name, string $credentialId): Passkey
    {
        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => $credentialId,
            'credential' => [],
        ]);
    }
}

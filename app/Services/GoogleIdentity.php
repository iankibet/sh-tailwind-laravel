<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Validation\ValidationException;

class GoogleIdentity
{
    /** @return array{sub: string, email: string, name: string} */
    public function verify(string $credential): array
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            throw ValidationException::withMessages([
                'credential' => ['Google sign-in is not configured.'],
            ]);
        }

        $payload = (new GoogleClient(['client_id' => $clientId]))->verifyIdToken($credential);

        if (! $payload || empty($payload['sub']) || empty($payload['email']) || empty($payload['email_verified'])) {
            throw ValidationException::withMessages([
                'credential' => ['Google could not verify this account.'],
            ]);
        }

        return [
            'sub' => (string) $payload['sub'],
            'email' => mb_strtolower((string) $payload['email']),
            'name' => (string) ($payload['name'] ?? $payload['email']),
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Recaptcha
{
    public function verify(?string $token, string $action): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (! $secret) {
            return;
        }

        if (! $token) {
            $this->fail();
        }

        try {
            $result = Http::asForm()
                ->timeout(5)
                ->post(config('services.recaptcha.verify_url'), [
                    'secret' => $secret,
                    'response' => $token,
                ])
                ->throw()
                ->json();
        } catch (RequestException) {
            $this->fail();
        }

        if (
            ! ($result['success'] ?? false)
            || ($result['action'] ?? null) !== $action
            || (float) ($result['score'] ?? 0) < config('services.recaptcha.minimum_score', 0.5)
        ) {
            Log::warning('reCAPTCHA verification failed', [
                'action' => $action,
                'hostname' => $result['hostname'] ?? null,
                'score' => $result['score'] ?? null,
                'error_codes' => $result['error-codes'] ?? [],
            ]);

            $this->fail($result['error-codes'] ?? []);
        }
    }

    /** @param array<int, string> $errorCodes */
    private function fail(array $errorCodes = []): never
    {
        $message = in_array('browser-error', $errorCodes, true)
            ? 'reCAPTCHA could not connect from this browser. Disable blockers or VPN and try again.'
            : 'We could not verify this request. Please try again.';

        throw ValidationException::withMessages([
            'recaptcha_token' => [$message],
            'email' => [$message],
        ]);
    }
}

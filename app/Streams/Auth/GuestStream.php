<?php

namespace App\Streams\Auth;

use App\Models\User;
use App\Services\GoogleIdentity;
use App\Services\Notifier;
use App\Services\Recaptcha;
use Iankibet\Shbackend\App\Repositories\ShRepository;
use Iankibet\Streamline\Attributes\Validate;
use Iankibet\Streamline\Stream;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GuestStream extends Stream
{
    public function __construct(
        private readonly GoogleIdentity $googleIdentity,
        private readonly Recaptcha $recaptcha,
    ) {}

    /** @return array{status: string, token: string, user: User} */
    #[Validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ])]
    public function login(): array
    {
        $this->recaptcha->verify(request()->input('recaptcha_token'), 'login');
        $credentials = $this->only('email', 'password');

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        $response = $this->authenticatedResponse($user);
        ShRepository::storeLog('user_login', "{$user->name} logged in", $user);

        return $response;
    }

    /** @return array{status: string, token: string, user: User} */
    #[Validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
        'password' => ['required', 'confirmed', 'min:8'],
    ])]
    public function register(): array
    {
        $this->recaptcha->verify(request()->input('recaptcha_token'), 'register');
        $data = $this->only('name', 'email', 'phone', 'password');

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => 'client',
            'password' => Hash::make($data['password']),
        ]);

        Notifier::send('welcome', $user, ['name' => $user->name]);

        $response = $this->authenticatedResponse($user);
        ShRepository::storeLog('user_registration', "{$user->name} registered", $user);

        return $response;
    }

    /** @return array{status: string, token: string, user: User} */
    #[Validate([
        'credential' => ['required', 'string'],
    ])]
    public function google(): array
    {
        $identity = $this->googleIdentity->verify($this->only('credential'));

        $user = User::query()
            ->where('google_id', $identity['sub'])
            ->orWhere('email', $identity['email'])
            ->first();

        if ($user) {
            if ($user->google_id && $user->google_id !== $identity['sub']) {
                throw ValidationException::withMessages([
                    'credential' => ['This email is linked to another Google account.'],
                ]);
            }

            $user->forceFill([
                'google_id' => $identity['sub'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::query()->create([
                'name' => $identity['name'],
                'email' => $identity['email'],
                'google_id' => $identity['sub'],
                'email_verified_at' => now(),
                'role' => 'client',
            ]);

            Notifier::send('welcome', $user, ['name' => $user->name]);
        }

        $response = $this->authenticatedResponse($user);
        ShRepository::storeLog('google_login', "{$user->name} signed in with Google", $user);

        return $response;
    }

    /** @return array{status: string, token: string, user: User, has_passkeys: bool} */
    private function authenticatedResponse(User $user): array
    {
        return [
            'status' => 'success',
            'token' => $user->createToken('vue')->plainTextToken,
            'user' => $user,
            'has_passkeys' => $user->hasPasskeysEnabled(),
        ];
    }

    protected function response($data, $status = 200)
    {
        abort(response($data, $status));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Iankibet\Shbackend\App\Repositories\ShRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;

class PasskeyController extends Controller
{
    public function loginOptions(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate();
        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function login(PasskeyVerificationRequest $request, VerifyPasskey $verify): JsonResponse
    {
        $passkey = $verify($request->credential(), $request->verificationOptions());

        if (! Passkeys::allowsLogin($request, $passkey)) {
            abort(403, 'Unable to sign in with this account.');
        }

        /** @var User $user */
        $user = $passkey->user;
        ShRepository::storeLog('passkey_login', "{$user->name} signed in with a passkey", $user);

        return response()->json([
            'status' => 'success',
            'token' => $user->createToken('vue-passkey')->plainTextToken,
            'user' => $user,
            'has_passkeys' => true,
        ]);
    }

    public function registrationOptions(Request $request, GenerateRegistrationOptions $generate): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $options = $generate($user);
        $request->session()->put('passkey.registration_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function store(PasskeyRegistrationRequest $request, StorePasskey $store): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $passkey = $store(
            $user,
            $request->string('name')->toString(),
            $request->credential(),
            $request->registrationOptions(),
        );
        ShRepository::storeLog('passkey_registered', "Registered passkey {$passkey->name}", $passkey);

        return response()->json(['status' => 'success', 'passkey' => $this->serialize($passkey)]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'passkeys' => $request->user()->passkeys()
                ->latest()
                ->get()
                ->map(fn (Passkey $passkey) => $this->serialize($passkey)),
        ]);
    }

    public function destroy(Request $request, Passkey $passkey, DeletePasskey $delete): JsonResponse
    {
        abort_unless((string) $passkey->user_id === (string) $request->user()->getKey(), 403);

        $name = $passkey->name;
        $delete($request->user(), $passkey);
        ShRepository::storeLog('passkey_deleted', "Removed passkey {$name}");

        return response()->json(['status' => 'success']);
    }

    /** @return array{id: int, name: string, authenticator: ?string, last_used_at: ?string, created_at: ?string} */
    private function serialize(Passkey $passkey): array
    {
        return [
            'id' => $passkey->id,
            'name' => $passkey->name,
            'authenticator' => $passkey->authenticator,
            'last_used_at' => $passkey->last_used_at?->toISOString(),
            'created_at' => $passkey->created_at?->toISOString(),
        ];
    }
}

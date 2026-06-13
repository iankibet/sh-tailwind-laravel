<?php

namespace App\Streams\Auth;

use App\Models\User;
use Iankibet\Shbackend\App\Repositories\ShRepository;
use Iankibet\Streamline\Stream;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserStream extends Stream
{
    /** @return array{status: string, user: User, has_passkeys: bool} */
    public function current(): array
    {
        /** @var User $user */
        $user = request()->user();

        return [
            'status' => 'success',
            'user' => $user->withResolvedPermissions(),
            'has_passkeys' => $user->hasPasskeysEnabled(),
        ];
    }

    /** @return array{status: string, user: User} */
    public function updateProfile(): array
    {
        /** @var User $user */
        $user = request()->user();
        $data = Validator::validate(request()->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        $user->update($data);
        ShRepository::storeLog('profile_updated', 'Updated their profile details', $user);

        return ['status' => 'success', 'user' => $user->fresh()];
    }

    /** @return array{status: string, message: string} */
    public function updatePassword(): array
    {
        /** @var User $user */
        $user = request()->user();
        $data = Validator::validate(request()->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $data['password']]);
        ShRepository::storeLog('password_updated', 'Updated their account password', $user);

        return ['status' => 'success', 'message' => 'Password updated successfully.'];
    }

    /** @return array{status: string} */
    public function logout(): array
    {
        ShRepository::storeLog('user_logout', 'Logged out');
        request()->user()->currentAccessToken()?->delete();

        return ['status' => 'success'];
    }
}

<?php

namespace App\Streams\Admin;

use App\Models\Core\Department;
use App\Models\User;
use Iankibet\Shbackend\App\Repositories\ShRepository;
use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Stream;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Permission('admins')]
class AdminsStream extends Stream
{
    public ?User $user = null;

    /** @var array<int, array{id: int, name: string}> */
    public array $departments = [];

    public function __construct(?int $userId = null)
    {
        if ($userId) {
            $this->departments = Department::query()->orderBy('name')->get(['id', 'name'])->all();
            $this->user = $this->findAdmin($userId);
        }
    }

    #[Permission('admins.list')]
    public function list(): Response
    {
        return User::query()
            ->whereIn('users.role', ['admin', 'super_admin'])
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.role',
                'users.created_at',
                'departments.name as department',
            ])
            ->tableResponse(['users.name', 'users.email', 'users.phone', 'users.role', 'departments.name']);
    }

    /** @return array{status: string, user: User} */
    #[Permission('admins.view')]
    public function get(int $id): array
    {
        return ['status' => 'success', 'user' => $this->findAdmin($id)];
    }

    /** @return array{status: string, user: User} */
    #[Permission('admins.update')]
    public function update(?int $id = null): array
    {
        $id ??= request()->integer('id');
        $user = User::query()->whereIn('role', ['admin', 'super_admin'])->findOrFail($id);
        $data = Validator::validate(request()->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user->id)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);

        $user->update($data);
        ShRepository::storeLog('admin_updated', "Updated administrator {$user->name}", $user);

        return ['status' => 'success', 'user' => $user->fresh()->load('department:id,name')];
    }

    /** @return array{status: string, message: string} */
    #[Permission('admins.reset_password')]
    public function updatePassword(?int $id = null): array
    {
        $id ??= request()->integer('id');
        $user = User::query()->whereIn('role', ['admin', 'super_admin'])->findOrFail($id);
        $data = Validator::validate(request()->all(), [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $data['password']]);
        ShRepository::storeLog('admin_password_updated', "Reset the password for administrator {$user->name}", $user);

        return ['status' => 'success', 'message' => 'Administrator password updated successfully.'];
    }

    /** @return array{status: string, data: array<int, array{id: int, name: string}>} */
    #[Permission('admins.update')]
    public function departmentOptions(): array
    {
        return [
            'status' => 'success',
            'data' => Department::query()->orderBy('name')->get(['id', 'name'])->all(),
        ];
    }

    /** @return array{status: string, user: User} */
    #[Permission('admins.update')]
    public function saveDetails(): array
    {
        $userId = $this->user?->id ?? request()->integer('id');
        abort_unless($userId, 404, 'Administrator not found.');
        $result = $this->update($userId);
        $this->user = $result['user'];

        return $result;
    }

    /** @return array{status: string, message: string} */
    #[Permission('admins.reset_password')]
    public function resetPassword(): array
    {
        $userId = $this->user?->id ?? request()->integer('id');
        abort_unless($userId, 404, 'Administrator not found.');

        return $this->updatePassword($userId);
    }

    private function findAdmin(int $id): User
    {
        return User::query()
            ->whereIn('role', ['admin', 'super_admin'])
            ->with('department:id,name')
            ->findOrFail($id);
    }
}

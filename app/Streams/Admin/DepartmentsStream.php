<?php

namespace App\Streams\Admin;

use App\Models\Core\Department;
use App\Models\Core\DepartmentPermission;
use Iankibet\Shbackend\App\Repositories\RoleRepository;
use Iankibet\Shbackend\App\Repositories\ShRepository;
use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Stream;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Permission('departments')]
class DepartmentsStream extends Stream
{
    public ?Department $department = null;

    /** @var array<int, array<string, mixed>> */
    public array $modules = [];

    public function __construct(?int $departmentId = null)
    {
        if ($departmentId) {
            $this->modules = $this->permissionOptions()['modules'];
            $this->department = $this->findDepartment($departmentId);
        }
    }

    #[Permission('departments.list')]
    public function list(): Response
    {
        return Department::query()
            ->select(['id', 'name', 'description', 'created_at'])
            ->withCount(['users', 'departmentPermissions as permissions_count'])
            ->tableResponse(['name', 'description']);
    }

    /** @return array{status: string, department: Department} */
    #[Permission('departments.view')]
    public function get(int $id): array
    {
        return [
            'status' => 'success',
            'department' => $this->findDepartment($id),
        ];
    }

    /** @return array{status: string, department: Department} */
    #[Permission('departments.create')]
    public function create(): array
    {
        return $this->persistDepartment();
    }

    /** @return array{status: string, department: Department} */
    #[Permission('departments.update')]
    public function updateDetails(): array
    {
        abort_unless($this->department, 404, 'Department not found.');

        $result = $this->persistDepartment($this->department);
        $this->department = $this->findDepartment($this->department->id);

        return $result;
    }

    /** @return array{status: string, department: Department} */
    private function persistDepartment(?Department $department = null): array
    {
        $department ??= new Department;

        $data = Validator::validate(request()->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department->id),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $department->fill($data)->save();
        ShRepository::storeLog(
            $department->wasRecentlyCreated ? 'department_created' : 'department_updated',
            ($department->wasRecentlyCreated ? 'Created' : 'Updated')." department {$department->name}",
            $department,
        );

        return [
            'status' => 'success',
            'department' => $department->fresh(),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     modules: array<int, array{module: string, label: string, permissions: array<int, string>, tree: array<int, mixed>}>
     * }
     */
    #[Permission('departments.manage_permissions')]
    public function permissionOptions(): array
    {
        return [
            'status' => 'success',
            'modules' => collect(RoleRepository::getRolePermissions('admin', true))
                ->reject(fn (string $module): bool => $module === 'common')
                ->map(function (string $module): array {
                    $permissions = RoleRepository::getModulePermissions('admin', $module);
                    $definition = json_decode(Storage::get("permissions/modules/{$module}.json"), true) ?: [];

                    return [
                        'module' => $module,
                        'label' => str($module)->replace(['-', '_'], ' ')->title()->toString(),
                        'permissions' => array_values(array_unique($permissions)),
                        'tree' => $this->permissionTree($definition['children'] ?? []),
                    ];
                })
                ->filter(fn (array $module): bool => $module['permissions'] !== [])
                ->values()
                ->all(),
        ];
    }

    /** @return array{status: string, permission: ?DepartmentPermission} */
    #[Permission('departments.manage_permissions')]
    public function saveModulePermissions(int $id, string $module): array
    {
        $department = Department::query()->findOrFail($id);
        $available = collect($this->permissionOptions()['modules'])->keyBy('module');

        abort_unless($available->has($module), 404, 'Permission module not found.');

        $data = Validator::validate(request()->all(), [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string'],
        ]);

        $allowedPermissions = collect($available[$module]['permissions']);
        $permissions = collect($data['permissions'])->unique()->values();

        abort_if(
            $permissions->contains(fn (string $permission): bool => ! $allowedPermissions->contains($permission)),
            422,
            "Invalid permission supplied for the {$module} module.",
        );

        $permission = DB::transaction(function () use ($department, $module, $permissions): ?DepartmentPermission {
            if ($permissions->isEmpty()) {
                $department->departmentPermissions()->where('module', $module)->delete();

                return null;
            }

            $urls = (new RoleRepository)->extractRoleUrls($module, $permissions->all(), 'admin');

            return DepartmentPermission::query()->updateOrCreate(
                ['department_id' => $department->id, 'module' => $module],
                [
                    'permissions' => json_encode($permissions->all(), JSON_THROW_ON_ERROR),
                    'urls' => json_encode(array_values(Arr::wrap($urls)), JSON_THROW_ON_ERROR),
                ],
            );
        });

        if ($permission) {
            $permission->setAttribute('permissions', $permissions->all());
        }

        ShRepository::storeLog(
            'department_permissions_updated',
            "Updated {$module} permissions for department {$department->name}",
            $department,
        );

        return ['status' => 'success', 'permission' => $permission];
    }

    /** @return array{status: string, permission: ?DepartmentPermission} */
    #[Permission('departments.manage_permissions')]
    public function updateModulePermissions(string $module): array
    {
        abort_unless($this->department, 404, 'Department not found.');

        $result = $this->persistModulePermissions($this->department, $module, request('permissions', []));
        $this->department = $this->findDepartment($this->department->id);

        return ['status' => 'success', 'permission' => $result];
    }

    /** @return array{status: string, department: Department} */
    #[Permission('departments.manage_permissions')]
    public function syncPermissions(int $id): array
    {
        $department = Department::query()->findOrFail($id);
        $available = collect($this->permissionOptions()['modules'])->keyBy('module');

        $data = Validator::validate(request()->all(), [
            'modules' => ['present', 'array'],
            'modules.*.module' => ['required', 'string', Rule::in($available->keys()->all())],
            'modules.*.permissions' => ['present', 'array'],
            'modules.*.permissions.*' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($department, $data, $available): void {
            $selectedModules = collect($data['modules'])
                ->filter(fn (array $module): bool => $module['permissions'] !== [])
                ->keyBy('module');

            $department->departmentPermissions()
                ->whereNotIn('module', $selectedModules->keys())
                ->delete();

            foreach ($selectedModules as $module => $selection) {
                $allowedPermissions = collect($available[$module]['permissions']);
                $permissions = collect($selection['permissions'])
                    ->filter(fn (string $permission): bool => $allowedPermissions->contains($permission))
                    ->unique()
                    ->values()
                    ->all();

                abort_if(
                    count($permissions) !== count(array_unique($selection['permissions'])),
                    422,
                    "Invalid permission supplied for the {$module} module.",
                );

                $urls = (new RoleRepository)->extractRoleUrls($module, $permissions, 'admin');

                DepartmentPermission::query()->updateOrCreate(
                    ['department_id' => $department->id, 'module' => $module],
                    [
                        'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                        'urls' => json_encode(array_values(Arr::wrap($urls)), JSON_THROW_ON_ERROR),
                    ],
                );
            }
        });

        ShRepository::storeLog(
            'department_permissions_updated',
            "Updated all permissions for department {$department->name}",
            $department,
        );

        return $this->get($department->id);
    }

    /** @return array{status: string} */
    #[Permission('departments.delete')]
    public function delete(int $id): array
    {
        $department = Department::query()->withCount('users')->findOrFail($id);

        abort_if($department->users_count > 0, 422, 'Move all users out of this department before deleting it.');

        DB::transaction(function () use ($department): void {
            $department->departmentPermissions()->delete();
            $department->delete();
        });

        ShRepository::storeLog('department_deleted', "Deleted department {$department->name}");

        return ['status' => 'success'];
    }

    /**
     * @param  array<string, array<string, mixed>>  $children
     * @return array<int, array{slug: string, label: string, children: array<int, mixed>}>
     */
    private function permissionTree(array $children, ?string $parent = null): array
    {
        return collect($children)
            ->filter(fn (array $permission): bool => in_array('admin', $permission['roles'] ?? [], true))
            ->map(function (array $permission, string $slug) use ($parent): array {
                $fullSlug = $parent ? "{$parent}.{$slug}" : $slug;

                return [
                    'slug' => $fullSlug,
                    'label' => str($slug)->replace(['-', '_'], ' ')->title()->toString(),
                    'children' => $this->permissionTree($permission['children'] ?? [], $fullSlug),
                ];
            })
            ->values()
            ->all();
    }

    private function findDepartment(int $id): Department
    {
        $department = Department::query()
            ->with(['departmentPermissions:id,department_id,module,permissions'])
            ->withCount('users')
            ->findOrFail($id);

        $department->departmentPermissions->each(function (DepartmentPermission $permission): void {
            $permission->setAttribute('permissions', json_decode($permission->getRawOriginal('permissions'), true) ?: []);
        });

        $department->setRelation('permissions', $department->departmentPermissions);
        $department->unsetRelation('departmentPermissions');

        return $department;
    }

    /** @param array<int, string> $permissions */
    private function persistModulePermissions(Department $department, string $module, array $permissions): ?DepartmentPermission
    {
        $available = collect($this->modules ?: $this->permissionOptions()['modules'])->keyBy('module');
        abort_unless($available->has($module), 404, 'Permission module not found.');

        $validated = Validator::make(
            ['permissions' => $permissions],
            ['permissions' => ['present', 'array'], 'permissions.*' => ['required', 'string']],
        )->validate();

        $allowedPermissions = collect($available[$module]['permissions']);
        $selected = collect($validated['permissions'])->unique()->values();

        abort_if(
            $selected->contains(fn (string $permission): bool => ! $allowedPermissions->contains($permission)),
            422,
            "Invalid permission supplied for the {$module} module.",
        );

        $permission = DB::transaction(function () use ($department, $module, $selected): ?DepartmentPermission {
            if ($selected->isEmpty()) {
                $department->departmentPermissions()->where('module', $module)->delete();

                return null;
            }

            $urls = (new RoleRepository)->extractRoleUrls($module, $selected->all(), 'admin');

            return DepartmentPermission::query()->updateOrCreate(
                ['department_id' => $department->id, 'module' => $module],
                [
                    'permissions' => json_encode($selected->all(), JSON_THROW_ON_ERROR),
                    'urls' => json_encode(array_values(Arr::wrap($urls)), JSON_THROW_ON_ERROR),
                ],
            );
        });

        if ($permission) {
            $permission->setAttribute('permissions', $selected->all());
        }

        ShRepository::storeLog(
            'department_permissions_updated',
            "Updated {$module} permissions for department {$department->name}",
            $department,
        );

        return $permission;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Core\Department;
use App\Models\Core\DepartmentPermission;
use App\Models\User;
use Iankibet\Shbackend\App\Repositories\RoleRepository;
use Iankibet\Shbackend\App\Repositories\ShRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AddAdmin extends Command
{
    protected $signature = 'sh:add-admin';

    protected $description = 'Create an administrator and grant a department its permissions';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Name', 'Super Admin'),
            'email' => $this->ask('Admin Email', 'admin@localhost.com'),
            'phone' => $this->ask('Admin Phone', '+254700000000'),
            'password' => $this->secret('Admin Password') ?: 'admin@localhost.com',
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:255', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $departmentName = $this->ask('Department Name', 'Super Admin');

        DB::transaction(function () use ($data, $departmentName): void {
            $department = Department::query()->create([
                'name' => $departmentName,
                'description' => $departmentName,
            ]);

            foreach (RoleRepository::getRolePermissions('admin', true) as $module) {
                $permissions = RoleRepository::getModulePermissions('admin', $module);

                if ($permissions === []) {
                    continue;
                }

                $urls = (new RoleRepository)->extractRoleUrls($module, $permissions, 'admin');

                DepartmentPermission::query()->create([
                    'department_id' => $department->id,
                    'module' => $module,
                    'permissions' => json_encode(array_values($permissions), JSON_THROW_ON_ERROR),
                    'urls' => json_encode(array_values($urls), JSON_THROW_ON_ERROR),
                ]);
            }

            $admin = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'admin',
                'department_id' => $department->id,
            ]);

            ShRepository::storeLog(
                'department_created',
                "Created department {$department->name} for a new administrator",
                $department,
            );
            ShRepository::storeLog('admin_created', "Created administrator {$admin->name}", $admin);
        });

        $this->info("Administrator created in the {$departmentName} department.");

        return self::SUCCESS;
    }
}

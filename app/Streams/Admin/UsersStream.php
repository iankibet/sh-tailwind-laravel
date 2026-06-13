<?php

namespace App\Streams\Admin;

use App\Models\User;
use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Stream;
use Illuminate\Http\Response;

#[Permission('users')]
class UsersStream extends Stream
{
    #[Permission('users.list')]
    public function list(): Response
    {
        return User::query()
            ->select(['id', 'name', 'email', 'phone', 'role', 'created_at'])
            ->tableResponse(['name', 'email', 'phone', 'role']);
    }
}

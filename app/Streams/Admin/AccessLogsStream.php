<?php

namespace App\Streams\Admin;

use App\Models\Core\Log;
use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Stream;
use Illuminate\Http\Response;

#[Permission('access_logs')]
class AccessLogsStream extends Stream
{
    #[Permission('access_logs.list')]
    public function list(): Response
    {
        return Log::query()
            ->leftJoin('users', 'users.id', '=', 'logs.user_id')
            ->select([
                'logs.id',
                'logs.slug',
                'logs.log',
                'logs.model',
                'logs.model_id',
                'logs.device',
                'logs.ip_address',
                'logs.created_at',
                'users.name as user',
            ])
            ->tableResponse(['logs.slug', 'logs.log', 'logs.device', 'logs.ip_address', 'users.name']);
    }
}

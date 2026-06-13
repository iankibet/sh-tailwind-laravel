<?php

namespace App\Listeners;

use App\Models\Core\Log;
use Iankibet\Shbackend\App\Events\ShNewLog;

class StoreShLog
{
    public function handle(ShNewLog $event): void
    {
        Log::query()->create([
            'user_id' => $event->payload['user_id'] ?? 0,
            'slug' => $event->payload['slug'],
            'log' => $event->payload['log'],
            'model_id' => $event->payload['model_id'] ?? null,
            'model' => $event->payload['model'] ?? null,
            'device' => $event->payload['device'] ?? null,
            'ip_address' => $event->payload['ip_address'] ?? null,
        ]);
    }
}

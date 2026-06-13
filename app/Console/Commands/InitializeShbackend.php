<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class InitializeShbackend extends Command
{
    protected $signature = 'sh:init';

    protected $description = 'Copy the default shbackend permission modules into application storage';

    public function handle(): int
    {
        $sourceDirectory = base_path('vendor/iankibet/shbackend/src/App/permissions/modules');
        $copied = 0;

        foreach (glob($sourceDirectory.'/*.json') ?: [] as $source) {
            $destination = 'permissions/modules/'.basename($source);

            if (Storage::exists($destination)) {
                $this->line("Skipped existing {$destination}");

                continue;
            }

            Storage::put($destination, file_get_contents($source));
            $this->info("Copied {$destination}");
            $copied++;
        }

        $this->info($copied === 0
            ? 'Permission modules are already initialized.'
            : "Initialized {$copied} permission module(s).");

        return self::SUCCESS;
    }
}

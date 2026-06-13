<?php

namespace App\Providers;

use App\Console\Commands\AddAdmin;
use App\Console\Commands\InitializeShbackend;
use Iankibet\Shbackend\App\Commands\AutoGenerateModel;
use Iankibet\Shbackend\App\Commands\BackupDatabase;
use Iankibet\Shbackend\App\Commands\CacheData;
use Iankibet\Shbackend\App\Commands\CachePermissions;
use Iankibet\Shbackend\App\Http\Middleware\ShAuth;
use Iankibet\Shbackend\App\Repositories\SearchRepo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ShbackendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('vendor/iankibet/shbackend/src/config/shconfig.php'),
            'shconfig',
        );
    }

    public function boot(Router $router): void
    {
        Builder::macro('tableResponse', function (?array $searchKeys = null) {
            /** @var Builder $this */
            return SearchRepo::of($this, null, $searchKeys)->response();
        });

        $router->aliasMiddleware('sh_auth', ShAuth::class);

        $this->loadMigrationsFrom(base_path('vendor/iankibet/shbackend/src/migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                AutoGenerateModel::class,
                BackupDatabase::class,
                CacheData::class,
                CachePermissions::class,
                AddAdmin::class,
                InitializeShbackend::class,
            ]);
        }
    }
}

<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MelhorEnvioService;

class MelhorEnvioServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MelhorEnvioService::class, function($app){
            return new MelhorEnvioService();
        });
    }
}

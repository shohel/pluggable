<?php

namespace Shohel\Pluggable;

use Illuminate\Support\ServiceProvider;

class PluggableServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        require __DIR__.'/Helper.php';
    }
}

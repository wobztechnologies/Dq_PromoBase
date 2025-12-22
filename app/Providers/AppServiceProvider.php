<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(!app()->isProduction());
        
        // Force HTTPS in production (Railway)
        if (app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            
            // Force secure cookies in production (HTTPS only)
            config(['session.secure' => true]);
            config(['session.same_site' => 'lax']);
        }
    }
}

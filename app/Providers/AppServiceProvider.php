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
            
            // Always enable secure cookies in production (Railway uses HTTPS)
            config(['session.secure' => true]);
            config(['session.same_site' => 'lax']);
            
            // Ensure cookies work correctly with Railway's proxy
            config(['session.domain' => null]); // Use default domain
        }
    }
}

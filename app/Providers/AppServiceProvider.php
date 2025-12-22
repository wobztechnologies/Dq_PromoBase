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
            
            // Configure secure cookies for HTTPS
            // Check if request is secure via proxy headers
            $request = request();
            $isSecure = $request->secure() || 
                       $request->header('X-Forwarded-Proto') === 'https' ||
                       $request->server('HTTPS') === 'on';
            
            if ($isSecure) {
                config(['session.secure' => true]);
            }
            config(['session.same_site' => 'lax']);
            config(['session.domain' => null]); // Use default domain
            
            // Ensure session cookie name is consistent
            config(['session.cookie' => env('SESSION_COOKIE', 'laravel_session')]);
        }
    }
}

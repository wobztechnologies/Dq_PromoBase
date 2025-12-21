<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateApiUserLastUsed
{
    /**
     * Handle an incoming request.
     *
     * Met à jour le timestamp last_used_at de l'utilisateur API à chaque requête.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Mettre à jour last_used_at après la réponse
        $user = auth('api')->user();
        
        if ($user && method_exists($user, 'updateLastUsed')) {
            $user->updateLastUsed();
        }

        return $response;
    }
}

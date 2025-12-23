<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (Railway uses a load balancer)
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                     \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                     \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                     \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );
        
        // Enregistrer les alias de middleware
        $middleware->alias([
            'api.update.last.used' => \App\Http\Middleware\UpdateApiUserLastUsed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pour les requêtes API, retourner les détails de l'erreur en JSON
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                }
                
                $response = [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ];
                
                // En mode debug, ajouter plus de détails
                if (config('app.debug')) {
                    $response['file'] = $e->getFile();
                    $response['line'] = $e->getLine();
                    $response['trace'] = collect($e->getTrace())->take(5)->toArray();
                }
                
                // Logger l'erreur
                \Illuminate\Support\Facades\Log::error('API Error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ]);
                
                return response()->json($response, $status);
            }
        });
    })->create();

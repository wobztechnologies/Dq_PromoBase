<?php

/**
 * PHP Preloading Script
 * 
 * This script preloads commonly used Laravel classes into OPcache
 * to improve performance on subsequent requests.
 */

// Only preload in FPM context, not CLI
if (php_sapi_name() === 'cli') {
    return;
}

$basePath = dirname(__DIR__, 2);

// Preload vendor autoload
if (file_exists($basePath . '/vendor/autoload.php')) {
    require_once $basePath . '/vendor/autoload.php';
}

// Preload Laravel framework core files
$preloadPaths = [
    $basePath . '/vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    $basePath . '/vendor/laravel/framework/src/Illuminate/Container/Container.php',
    $basePath . '/vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php',
    $basePath . '/vendor/laravel/framework/src/Illuminate/Http/Request.php',
    $basePath . '/vendor/laravel/framework/src/Illuminate/Http/Response.php',
    $basePath . '/vendor/laravel/framework/src/Illuminate/Routing/Router.php',
    $basePath . '/vendor/laravel/framework/src/Illuminate/Routing/Route.php',
];

foreach ($preloadPaths as $path) {
    if (file_exists($path)) {
        opcache_compile_file($path);
    }
}



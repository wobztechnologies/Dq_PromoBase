<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/dev/stderr');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
try {
    $app = require_once __DIR__.'/../bootstrap/app.php';
    
    if (!$app) {
        throw new RuntimeException('Failed to bootstrap Laravel application');
    }
    
    // Use the standard Laravel 12 method
    $request = Request::capture();
    $app->handleRequest($request);
    
} catch (Throwable $e) {
    // Log error to stderr (visible in Railway logs)
    error_log("FATAL ERROR: " . $e->getMessage());
    error_log("File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Return 500 error
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Internal Server Error\n";
    if (env('APP_DEBUG', false)) {
        echo "\n" . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    exit(1);
}

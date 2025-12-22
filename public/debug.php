<?php
// Debug script to see what's wrong
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/dev/stderr');

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Working Directory: " . getcwd() . "\n";

// Check if Laravel bootstrap exists
$bootstrap = __DIR__ . '/../bootstrap/app.php';
if (file_exists($bootstrap)) {
    echo "Bootstrap file exists\n";
    try {
        require $bootstrap;
        echo "Bootstrap loaded successfully\n";
    } catch (Throwable $e) {
        echo "ERROR loading bootstrap: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "ERROR: Bootstrap file not found at: $bootstrap\n";
}

// Check .env
$env = __DIR__ . '/../.env';
if (file_exists($env)) {
    echo ".env file exists\n";
} else {
    echo "WARNING: .env file not found\n";
}

// Check storage permissions
$storage = __DIR__ . '/../storage';
if (is_writable($storage)) {
    echo "Storage is writable\n";
} else {
    echo "ERROR: Storage is not writable\n";
}

// Check bootstrap/cache
$cache = __DIR__ . '/../bootstrap/cache';
if (is_writable($cache)) {
    echo "Bootstrap cache is writable\n";
} else {
    echo "ERROR: Bootstrap cache is not writable\n";
}


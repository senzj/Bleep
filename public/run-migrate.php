<?php

use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Safety check - production environment
if (app()->environment('production') === true) {
    exit('No.');
}

// Security: Require secret key
$secretKey = env('MIGRATION_SECRET_KEY');

if (!$secretKey) {
    http_response_code(500);
    exit('MIGRATION_SECRET_KEY not configured');
}

// Check if secret key is provided
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    exit('Unauthorized: Invalid or missing secret key');
}

// Log the migration run
error_log('Migration executed at ' . date('Y-m-d H:i:s'));

Artisan::call('migrate', [
    '--force' => true,
]);

echo nl2br(e(Artisan::output()));

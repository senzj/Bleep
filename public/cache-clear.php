<?php
// cache_clear.php (in your public folder)
if (isset($_GET['key']) && $_GET['key'] === 'your_secret_key') {
    // Clear views cache
    $viewsPath = __DIR__ . '/../bootstrap/cache/views';
    if (is_dir($viewsPath)) {
        array_map('unlink', glob("$viewsPath/*"));
        rmdir($viewsPath);
    }

    // Clear config cache
    $configPath = __DIR__ . '/../bootstrap/cache/config.php';
    if (file_exists($configPath)) unlink($configPath);

    echo "Cache cleared successfully!";
    exit;
}
echo "No.";
?>

<?php

/**
 * Installs production Composer dependencies on hosts without SSH.
 *
 * Standalone on purpose: it must not boot Laravel, because Composer rewrites
 * vendor/ while it runs. Reuses the same task key as the other URL scripts.
 *
 * Flags (query string):
 *   force=1      run even if composer.lock is unchanged (default: --if-stale)
 *   dry_run=1    show what Composer would do, change nothing
 *   timeout=300  max seconds (default 300, capped at 900)
 */

declare(strict_types=1);

$root = dirname(__DIR__); // adjust if this file is not one level below composer.json

ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$fail = static function (int $code, string $message): never {
    http_response_code($code);
    exit($message . "\n");
};

// ---- Key (read from .env without booting Laravel) --------------------------
$expected = '';
foreach (@file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with($line, 'SCRIPTS_TASK_KEY=')) {
        $expected = trim(substr($line, strlen('SCRIPTS_TASK_KEY=')), " \t\"'");
        break;
    }
}

$provided = (string) ($_SERVER['HTTP_X_TASK_KEY'] ?? $_GET['key'] ?? '');

if (strlen($expected) < 24) {
    $fail(503, 'Task key is not configured.');
}

if (!hash_equals($expected, $provided)) {
    usleep(500_000); // slow down guessing
    $fail(403, 'Forbidden');
}

// ---- Flags -----------------------------------------------------------------
$force = ($_GET['force'] ?? '') === '1';
$dryRun = ($_GET['dry_run'] ?? '') === '1';
$timeout = min(max((int) ($_GET['timeout'] ?? 300), 30), 900);

set_time_limit($timeout);
ini_set('memory_limit', '1G');

// ---- Single run at a time --------------------------------------------------
$lock = fopen($root . '/storage/composer-install.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    $fail(409, 'Another install is already running.');
}

// ---- --if-stale: skip when composer.lock has not changed -------------------
$hashFile = $root . '/storage/composer-install.hash';
$lockHash = is_file($root . '/composer.lock') ? sha1_file($root . '/composer.lock') : '';

if (!$force && $lockHash !== '' && is_file($root . '/vendor/autoload.php')
    && @file_get_contents($hashFile) === $lockHash) {
    exit("Up to date: composer.lock unchanged since the last install. Use force=1 to run anyway.\n");
}

// ---- composer.phar ---------------------------------------------------------
$phar = $root . '/storage/composer.phar';
if (!is_file($phar) && !@copy('https://getcomposer.org/composer-stable.phar', $phar)) {
    $fail(502, 'Could not download composer.phar (outbound requests may be blocked).');
}

putenv('COMPOSER_HOME=' . $root . '/storage/composer-home');
putenv('COMPOSER_NO_INTERACTION=1');
putenv('COMPOSER_NO_AUDIT=1');
chdir($root);

require 'phar://' . $phar . '/vendor/autoload.php';

/** @var \Symfony\Component\Console\Application $app */
/** @noinspection PhpUndefinedClassInspection */
$app = new \Composer\Console\Application(); // @phpstan-ignore-line
$app->setAutoExit(false);

$args = [
    'command' => 'install',
    '--no-dev' => true,
    '--optimize-autoloader' => true,
    '--no-scripts' => true,
    '--no-interaction' => true,
    '--working-dir' => $root,
];
if ($dryRun) {
    $args['--dry-run'] = true;
}

$exit = $app->run(
    new Symfony\Component\Console\Input\ArrayInput($args),
    new Symfony\Component\Console\Output\StreamOutput(fopen('php://output', 'w'))
);

if ($exit === 0 && !$dryRun) {
    file_put_contents($hashFile, $lockHash);

    // --no-scripts skipped package:discover; drop the stale caches so Laravel rebuilds them.
    foreach (['packages.php', 'services.php'] as $cache) {
        @unlink($root . '/bootstrap/cache/' . $cache);
    }
}

echo "\nExit code: {$exit}\n";
http_response_code($exit === 0 ? 200 : 500);

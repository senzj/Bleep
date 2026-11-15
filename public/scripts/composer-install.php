<?php

/**
 * Installs production Composer dependencies on hosts without SSH.
 *
 * Standalone on purpose: it must not boot Laravel, because Composer rewrites
 * vendor/ while it runs. Uses the same secret as the other URL scripts
 * (SCRIPT_SECRET_KEY).
 *
 * Flags (query string):
 *   force=1      run even if composer.lock is unchanged (default: --if-stale)
 *   dry_run=1    show what Composer would do, change nothing
 *   timeout=300  max seconds (default 300, capped at 900)
 *
 * Key: ?key=... or the X-Task-Key header.
 */

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstallComposerDependencies extends Command
{
    protected $signature = 'composer:install-dependencies';

    protected $description = 'Install missing production Composer dependencies';

    public function handle(): int
    {
        $composerJson = base_path('composer.json');
        $composerLock = base_path('composer.lock');

        if (! file_exists($composerJson) || ! file_exists($composerLock)) {
            $this->error('composer.json or composer.lock is missing.');

            return self::FAILURE;
        }

        $lockPath = storage_path('framework/composer-install.lock');
        $lock = fopen($lockPath, 'c');

        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another Composer installation is already running.');

            return self::FAILURE;
        }

        try {
            $composer = config('scripts.composer_binary', 'composer');

            $process = new Process([
                $composer,
                'install',
                '--no-dev',
                '--prefer-dist',
                '--optimize-autoloader',
                '--no-interaction',
                '--no-progress',
            ], base_path());

            $process->setTimeout(900);

            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                $this->error('Composer installation failed.');

                return self::FAILURE;
            }

            $this->info('Composer dependencies installed successfully.');

            return self::SUCCESS;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

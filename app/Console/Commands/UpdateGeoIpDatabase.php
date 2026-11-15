<?php

namespace App\Console\Commands;

use App\Services\GeoIpService;
use Carbon\CarbonInterface;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Installs the latest DB-IP City Lite database from db-ip.com.
 *
 * The download is decompressed and validated with the MaxMind reader before
 * the live database is replaced, so a broken download can never take IP
 * geolocation down. Schedule it monthly; DB-IP publishes a new build on the
 * first of each month.
 */
class UpdateGeoIpDatabase extends Command
{
    protected $signature = 'geoip:update
        {--month= : Build month to download in YYYY-MM form (defaults to the current month)}
        {--if-stale : Skip the download when the installed database is already from this month}
        {--force : Download even when --if-stale would skip}';

    protected $description = 'Download and install the latest DB-IP City Lite GeoIP database';

    public function handle(GeoIpService $geoIp): int
    {
        $target = (string) config('geoip.database');

        if ($target === '') {
            $this->components->error('No GeoIP database path is configured.');

            return self::FAILURE;
        }

        $month = $this->targetMonth();

        if ($month === null) {
            return self::FAILURE;
        }

        if ($this->option('if-stale') && ! $this->option('force') && $this->isCurrent($target, $month)) {
            $this->components->info('The GeoIP database is already built for '.$month->format('F Y').'.');

            return self::SUCCESS;
        }

        $template = (string) config('geoip.download_url');
        $installed = null;

        foreach ([$month, $month->copy()->subMonthNoOverflow()] as $candidate) {
            $url = $this->downloadUrl($template, $candidate);

            $this->line('Downloading '.$candidate->format('F Y').' from '.$url.' ...');

            try {
                $this->install($url, $target);
                $installed = $candidate;
                break;
            } catch (Throwable $exception) {
                $this->components->warn('Download failed: '.$exception->getMessage());
            }
        }

        if ($installed === null) {
            $this->components->error('Could not install a GeoIP database. The existing file was left untouched.');

            return self::FAILURE;
        }

        $state = $geoIp->state();

        $this->newLine();
        $this->components->info(
            'Installed '.basename($target).' ('
            .($state['size'] >= 1024 * 1024
                ? number_format($state['size'] / 1024 / 1024, 1).' MB'
                : number_format($state['size'] / 1024, 1).' KB')
            .', build '.($state['build_epoch'] !== null
                ? Carbon::createFromTimestamp($state['build_epoch'])->toFormattedDateString()
                : $installed->format('F Y'))
            .').'
        );

        return self::SUCCESS;
    }

    private function targetMonth(): ?Carbon
    {
        $option = $this->option('month');

        if ($option === null || $option === '') {
            return now()->startOfMonth();
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $option)) {
            $this->components->error('The --month option must look like 2026-11.');

            return null;
        }

        return Carbon::createFromFormat('!Y-m', (string) $option)->startOfMonth();
    }

    private function downloadUrl(string $template, CarbonInterface $month): string
    {
        if (! str_contains($template, '%s')) {
            return $template;
        }

        return sprintf($template, $month->format('Y-m'));
    }

    private function isCurrent(string $target, CarbonInterface $month): bool
    {
        if (! is_file($target)) {
            return false;
        }

        try {
            $reader = new Reader($target);

            try {
                $epoch = (int) $reader->metadata()->buildEpoch;
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return false;
        }

        return $epoch >= Carbon::instance($month)->startOfMonth()->timestamp;
    }

    private function install(string $url, string $target): void
    {
        $gzip = $this->temporaryPath('gz');
        $database = $this->temporaryPath('mmdb');

        try {
            $response = Http::timeout(300)
                ->connectTimeout(20)
                ->withUserAgent('Bleep GeoIP Updater (+'.(string) config('app.url').')')
                ->sink($gzip)
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('The server answered with HTTP '.$response->status().'.');
            }

            if (! is_file($gzip) || filesize($gzip) < 1024) {
                throw new RuntimeException('The downloaded archive was empty.');
            }

            $this->decompress($gzip, $database);
            $this->validate($database);

            File::ensureDirectoryExists(dirname($target));
            $this->replace($database, $target);
        } finally {
            @unlink($gzip);
            @unlink($database);
        }
    }

    private function decompress(string $source, string $destination): void
    {
        $input = gzopen($source, 'rb');

        if ($input === false) {
            throw new RuntimeException('The downloaded archive could not be opened.');
        }

        $output = fopen($destination, 'wb');

        if ($output === false) {
            gzclose($input);

            throw new RuntimeException('The temporary database file could not be created.');
        }

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('The downloaded archive is corrupt.');
                }

                if ($chunk !== '' && fwrite($output, $chunk) === false) {
                    throw new RuntimeException('The database could not be written to disk.');
                }
            }
        } finally {
            gzclose($input);
            fclose($output);
        }
    }

    private function validate(string $database): void
    {
        if (! is_file($database) || filesize($database) < 1024 * 1024) {
            throw new RuntimeException('The extracted database is too small to be valid.');
        }

        $reader = new Reader($database);

        try {
            $metadata = $reader->metadata();

            if ($metadata->buildEpoch <= 0) {
                throw new RuntimeException('The database metadata has no build date.');
            }

            try {
                $reader->city('8.8.8.8');
            } catch (AddressNotFoundException) {
                // A successful "not found" still proves the database is readable.
            }
        } finally {
            $reader->close();
        }
    }

    private function replace(string $database, string $target): void
    {
        $backup = $target.'.bak';

        if (is_file($target)) {
            @unlink($backup);

            if (! rename($target, $backup)) {
                throw new RuntimeException('The existing database could not be moved aside.');
            }
        }

        if (! rename($database, $target)) {
            if (is_file($backup)) {
                rename($backup, $target);
            }

            throw new RuntimeException('The new database could not be moved into place.');
        }

        @unlink($backup);
    }

    private function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'bleep-geoip-');

        if ($base === false) {
            throw new RuntimeException('A temporary file could not be created.');
        }

        @unlink($base);

        return $base.'.'.$extension;
    }
}

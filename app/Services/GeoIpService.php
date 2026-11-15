<?php

namespace App\Services;

use Closure;
use GeoIp2\Database\Reader;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Resolves public IP addresses to a human-readable location using a local
 * DB-IP City Lite database (MaxMind DB format).
 *
 * Every lookup is memoised for the lifetime of the instance, and the database
 * handle is opened once. Private, reserved and loopback addresses are never
 * looked up, so local development stays quiet.
 */
class GeoIpService
{
    private ?Reader $reader = null;

    private bool $readerFailed = false;

    /**
     * @var array<string, array{
     *     country: string|null,
     *     country_code: string|null,
     *     region: string|null,
     *     city: string|null,
     *     latitude: float|null,
     *     longitude: float|null,
     *     timezone: string|null,
     *     label: string,
     *     flag: string|null
     * }|null>
     */
    private array $cache = [];

    /**
     * @param  Closure(string): (array<string, mixed>|null)|null  $resolver
     */
    public function __construct(
        private ?string $database = null,
        private ?Closure $resolver = null,
    ) {}

    /**
     * @return array{
     *     country: string|null,
     *     country_code: string|null,
     *     region: string|null,
     *     city: string|null,
     *     latitude: float|null,
     *     longitude: float|null,
     *     timezone: string|null,
     *     label: string,
     *     flag: string|null
     * }|null
     */
    public function locate(?string $ip): ?array
    {
        if (! config('geoip.enabled', true)) {
            return null;
        }

        if (! self::isPublicAddress($ip)) {
            return null;
        }

        if ($this->resolver !== null) {
            return ($this->resolver)($ip);
        }

        if (array_key_exists($ip, $this->cache)) {
            return $this->cache[$ip];
        }

        try {
            $record = $this->reader()->city($ip);
        } catch (Throwable) {
            return $this->cache[$ip] = null;
        }

        $label = $this->label(
            $record->city->name,
            $record->mostSpecificSubdivision->name,
            $record->country->name,
        );

        if ($label === null) {
            return $this->cache[$ip] = null;
        }

        return $this->cache[$ip] = [
            'country' => $record->country->name,
            'country_code' => $record->country->isoCode,
            'region' => $record->mostSpecificSubdivision->name,
            'city' => $record->city->name,
            'latitude' => $record->location->latitude,
            'longitude' => $record->location->longitude,
            'timezone' => $record->location->timeZone,
            'label' => $label,
            'flag' => $this->flag($record->country->isoCode),
        ];
    }

    /**
     * Whether a location can be shown for this address at all.
     */
    public static function isPublicAddress(?string $ip): bool
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Facts about the configured database, used by the admin health pages.
     *
     * @return array{
     *     path: string|null,
     *     exists: bool,
     *     size: int,
     *     modified_at: Carbon|null,
     *     age_days: int|null,
     *     stale: bool,
     *     build_epoch: int|null
     * }
     */
    public function state(): array
    {
        $path = $this->databasePath();
        $exists = $path !== null && is_file($path);
        $modifiedAt = $exists ? Carbon::createFromTimestamp((int) filemtime($path)) : null;
        $staleAfter = max(1, (int) config('geoip.stale_after_days', 40));

        return [
            'path' => $path,
            'exists' => $exists,
            'size' => $exists ? (int) filesize($path) : 0,
            'modified_at' => $modifiedAt,
            'age_days' => $modifiedAt !== null ? (int) $modifiedAt->diffInDays(now()) : null,
            'stale' => $modifiedAt !== null && $modifiedAt->lt(now()->subDays($staleAfter)),
            'build_epoch' => $this->buildEpoch($path),
        ];
    }

    /**
     * The database file currently in use. Falls back to the newest *.mmdb in
     * the configured directory when the stable file has not been installed.
     */
    public function databasePath(): ?string
    {
        if ($this->database !== null) {
            return is_file($this->database) ? $this->database : $this->newestDatabaseIn(dirname($this->database));
        }

        $configured = (string) config('geoip.database');

        if ($configured === '') {
            return null;
        }

        if (is_file($configured)) {
            return $configured;
        }

        return $this->newestDatabaseIn(dirname($configured));
    }

    private function reader(): Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        if ($this->readerFailed) {
            throw new \RuntimeException('The GeoIP database could not be opened.');
        }

        $path = $this->databasePath();

        if ($path === null) {
            $this->readerFailed = true;

            throw new \RuntimeException('No GeoIP database is installed.');
        }

        try {
            return $this->reader = new Reader($path);
        } catch (Throwable $exception) {
            $this->readerFailed = true;

            throw $exception;
        }
    }

    private function buildEpoch(?string $path): ?int
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);

            try {
                return (int) $reader->metadata()->buildEpoch;
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return null;
        }
    }

    private function newestDatabaseIn(string $directory): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.mmdb') ?: [];

        if ($files === []) {
            return null;
        }

        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    private function label(?string ...$parts): ?string
    {
        $parts = array_values(array_unique(array_filter(
            $parts,
            fn (?string $part): bool => $part !== null && trim($part) !== '',
        )));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function flag(?string $code): ?string
    {
        if ($code === null || strlen($code) !== 2) {
            return null;
        }

        $flag = '';

        foreach (str_split(strtoupper($code)) as $letter) {
            $flag .= mb_chr(0x1F1E6 + ord($letter) - 65);
        }

        return $flag;
    }
}

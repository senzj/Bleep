<?php

namespace App\Services\System;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Read-only access to the tail of the application log.
 *
 * The file is never loaded in full: only the last N bytes are read, then split
 * into log entries (a header line followed by its stack trace).
 */
class LogFileReader
{
    public function __construct(private ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? (string) config('system.log_path');
    }

    public function exists(): bool
    {
        return is_file($this->path()) && is_readable($this->path());
    }

    public function size(): int
    {
        return $this->exists() ? (int) filesize($this->path()) : 0;
    }

    public function modifiedAt(): ?CarbonInterface
    {
        if (! $this->exists()) {
            return null;
        }

        return Carbon::createFromTimestamp((int) filemtime($this->path()));
    }

    /**
     * @return array<int, array{
     *     timestamp: string|null,
     *     environment: string|null,
     *     level: string,
     *     message: string,
     *     context: string,
     *     raw: string
     * }>
     */
    public function entries(int $limit = 200, ?string $level = null, ?string $search = null): array
    {
        $entries = array_reverse($this->parse($this->tail()));

        if ($level !== null && $level !== '') {
            $level = strtoupper($level);
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => strtoupper($entry['level']) === $level,
            ));
        }

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => str_contains(mb_strtolower($entry['raw']), $needle),
            ));
        }

        return array_slice($entries, 0, max(1, $limit));
    }

    /**
     * @return array<string, int>
     */
    public function levelCounts(): array
    {
        $counts = [];

        foreach ($this->parse($this->tail()) as $entry) {
            $counts[$entry['level']] = ($counts[$entry['level']] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    private function tail(): string
    {
        if (! $this->exists()) {
            return '';
        }

        $maxBytes = max(1024, (int) config('system.log_tail_bytes', 524288));
        $handle = fopen($this->path(), 'rb');

        if ($handle === false) {
            return '';
        }

        $size = (int) filesize($this->path());
        $offset = max(0, $size - $maxBytes);

        fseek($handle, $offset);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        // When the tail starts mid-line, drop that partial line.
        if ($offset > 0) {
            $firstBreak = strpos($contents, "\n");
            $contents = $firstBreak === false ? '' : substr($contents, $firstBreak + 1);
        }

        return $contents;
    }

    /**
     * @return array<int, array{
     *     timestamp: string|null,
     *     environment: string|null,
     *     level: string,
     *     message: string,
     *     context: string,
     *     raw: string
     * }>
     */
    private function parse(string $contents): array
    {
        if (trim($contents) === '') {
            return [];
        }

        $header = '/^\[(?<time>[^\]]+)\]\s+(?<env>[^\.\s]+)\.(?<level>[A-Z]+):\s?(?<message>.*)$/s';
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match($header, $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'timestamp' => $matches['time'],
                    'environment' => $matches['env'],
                    'level' => $matches['level'],
                    'message' => $matches['message'],
                    'context' => '',
                    'raw' => $line,
                ];
            } elseif ($current !== null) {
                $current['context'] .= $line."\n";
                $current['raw'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $current['context'] = trim($current['context']);
            $entries[] = $current;
        }

        return $entries;
    }
}

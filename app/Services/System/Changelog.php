<?php

namespace App\Services\System;

use Illuminate\Support\Arr;

class Changelog
{
    /**
     * Read and cache the changelog for the current request.
     *
     * @return array{current: string, releases: array<int, array<string, mixed>>}
     */
    public function all(): array
    {
        static $changelog = null;

        if ($changelog !== null) {
            return $changelog;
        }

        $path = resource_path('changelog.json');

        if (! is_file($path)) {
            return $changelog = ['current' => '0.0', 'releases' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return $changelog = ['current' => '0.0', 'releases' => []];
        }

        return $changelog = [
            'current' => (string) Arr::get($decoded, 'current', '0.0'),
            'releases' => array_values((array) Arr::get($decoded, 'releases', [])),
        ];
    }

    /**
     * The newest release entry, or null when the changelog is empty.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        return $this->all()['releases'][0] ?? null;
    }

    public function currentVersion(): string
    {
        return $this->all()['current'];
    }
}

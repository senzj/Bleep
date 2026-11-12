<?php

namespace App\Services\System;

use Illuminate\Support\Arr;

class Changelog
{
    public const BLEEP = 'bleep';

    public const BULLETIN = 'bulletin';

    /**
     * Read and cache each product's changelog for the current request.
     *
     * @return array<string, array{name: string, current: string, releases: array<int, array<string, mixed>>}>
     */
    public function products(): array
    {
        static $products = null;

        if ($products !== null) {
            return $products;
        }

        $decoded = $this->decode();

        return $products = [
            self::BLEEP => $this->normalise(
                Arr::get($decoded, 'products.bleep', $decoded),
                'Bleep',
            ),
            self::BULLETIN => $this->normalise(
                Arr::get($decoded, 'products.bulletin', []),
                'Bulletin board',
            ),
        ];
    }

    /**
     * The Bleep release log.
     *
     * @return array{name: string, current: string, releases: array<int, array<string, mixed>>}
     */
    public function all(): array
    {
        return $this->product(self::BLEEP);
    }

    /**
     * A single product release log.
     *
     * @return array{name: string, current: string, releases: array<int, array<string, mixed>>}
     */
    public function product(string $product): array
    {
        return $this->products()[$product] ?? $this->normalise([], $product);
    }

    /**
     * The bulletin board release log.
     *
     * @return array{name: string, current: string, releases: array<int, array<string, mixed>>}
     */
    public function bulletin(): array
    {
        return $this->product(self::BULLETIN);
    }

    /**
     * The newest Bleep release entry, or null when the log is empty.
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

    /**
     * The newest bulletin release entry, or null when the log is empty.
     *
     * @return array<string, mixed>|null
     */
    public function bulletinLatest(): ?array
    {
        return $this->bulletin()['releases'][0] ?? null;
    }

    public function bulletinVersion(): string
    {
        return $this->bulletin()['current'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $path = resource_path('changelog.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{name: string, current: string, releases: array<int, array<string, mixed>>}
     */
    private function normalise(array $product, string $fallbackName): array
    {
        return [
            'name' => (string) Arr::get($product, 'name', $fallbackName),
            'current' => (string) Arr::get($product, 'current', '0.0'),
            'releases' => array_values((array) Arr::get($product, 'releases', [])),
        ];
    }
}

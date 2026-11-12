<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Every stored setting, decoded and cached for the current request.
     *
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        try {
            return Cache::rememberForever('system:settings:values', function (): array {
                return static::query()
                    ->get()
                    ->mapWithKeys(fn (self $setting) => [$setting->key => $setting->value])
                    ->all();
            });
        } catch (Throwable) {
            // The table may not exist yet (fresh install or a database
            // outage). Fall back to the definition defaults.
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }

        static::forgetCache();
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();

        static::forgetCache();
    }

    public static function forgetCache(): void
    {
        try {
            Cache::forget('system:settings:values');
        } catch (Throwable) {
            // Cache store unavailable; the next read will simply hit the DB.
        }
    }
}

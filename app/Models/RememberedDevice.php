<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RememberedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'ip',
        'user_agent',
        'last_used_at',
        'parsed_os',
        'parsed_browser',
        'parsed_device_type',
        'revoked_at', // added
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime', // added
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Optional: parse UA during model events (example stub)
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->parsed_os && $model->user_agent) {
                // Simple heuristics, replace with robust parser (UAParser) if available
                $ua = strtolower($model->user_agent);
                if (str_contains($ua, 'android')) {
                    $model->parsed_os = 'Android';
                    $model->parsed_device_type = 'mobile';
                } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
                    $model->parsed_os = 'iOS';
                    $model->parsed_device_type = 'mobile';
                } elseif (str_contains($ua, 'mac os') || str_contains($ua, 'macintosh')) {
                    $model->parsed_os = 'macOS';
                    $model->parsed_device_type = 'desktop';
                } elseif (str_contains($ua, 'windows')) {
                    $model->parsed_os = 'Windows';
                    $model->parsed_device_type = 'desktop';
                } else {
                    $model->parsed_os = 'Unknown';
                    $model->parsed_device_type = 'unknown';
                }

                if (str_contains($ua, 'chrome/')) $model->parsed_browser = 'Chrome';
                elseif (str_contains($ua, 'firefox/')) $model->parsed_browser = 'Firefox';
                elseif (str_contains($ua, 'safari/') && !str_contains($ua, 'chrome')) $model->parsed_browser = 'Safari';
                elseif (str_contains($ua, 'edge/')) $model->parsed_browser = 'Edge';
                else $model->parsed_browser = 'Other';
            }
        });
    }

    public static function createOrUpdateFromRequest(\Illuminate\Http\Request $request, string $plainToken): self
    {
        $hashed = hash('sha256', $plainToken);
        $ua = $request->userAgent() ?? '';
        $parsed = [
            'parsed_os' => null,
            'parsed_browser' => null,
            'parsed_device_type' => null,
        ];

        // Best to use a library; fallback to heuristics
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'android')) { $parsed['parsed_os'] = 'Android'; $parsed['parsed_device_type'] = 'mobile'; }
        elseif (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad')) { $parsed['parsed_os'] = 'iOS'; $parsed['parsed_device_type'] = 'mobile'; }
        elseif (str_contains($uaLower, 'mac os') || str_contains($uaLower, 'macintosh')) { $parsed['parsed_os'] = 'macOS'; $parsed['parsed_device_type'] = 'desktop'; }
        elseif (str_contains($uaLower, 'windows')) { $parsed['parsed_os'] = 'Windows'; $parsed['parsed_device_type'] = 'desktop'; }
        else { $parsed['parsed_os'] = 'Unknown'; $parsed['parsed_device_type'] = 'unknown'; }

        if (str_contains($uaLower, 'chrome/')) $parsed['parsed_browser'] = 'Chrome';
        elseif (str_contains($uaLower, 'firefox/')) $parsed['parsed_browser'] = 'Firefox';
        elseif (str_contains($uaLower, 'safari/') && !str_contains($uaLower, 'chrome')) $parsed['parsed_browser'] = 'Safari';
        elseif (str_contains($uaLower, 'edge/')) $parsed['parsed_browser'] = 'Edge';
        else $parsed['parsed_browser'] = 'Other';

        // If the client already had a device token cookie, update that record; otherwise create new
        $cookieToken = $request->cookie('device_token');
        $cookieHashed = $cookieToken ? hash('sha256', $cookieToken) : null;

        $data = [
            'token' => $hashed,
            'ip' => $request->ip(),
            'user_agent' => $ua,
            'last_used_at' => now(),
            'parsed_os' => $parsed['parsed_os'],
            'parsed_browser' => $parsed['parsed_browser'],
            'parsed_device_type' => $parsed['parsed_device_type'],
        ];

        if ($cookieHashed) {
            $device = static::where('token', $cookieHashed)->where('user_id', $request->user()->id)->first();
            if ($device) {
                $device->update($data);
                return $device->refresh();
            }
        }

        // Create new device row
        return static::create(array_merge([
            'user_id' => $request->user()->id,
        ], $data));
    }

    public static function findByPlainToken(string $plain): ?self
    {
        return static::where('token', hash('sha256', $plain))->first();
    }
}

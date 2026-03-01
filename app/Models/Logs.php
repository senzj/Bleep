<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\UserAgentParser;

class Logs extends Model
{
    protected $table = 'logs';

    protected $fillable = [
        'user_id',
        'action',
        'details',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    // add relation so with('user') works
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Record an activity to the logs table.
     *
     * @param int|null $userId
     * @param string $action
     * @param array|null $details
     * @param Request|null $request
     * @return self|null
     */
    public static function record(?int $userId, string $action, ?array $details = null, ?Request $request = null): ?self
    {
        try {
            if ($request) {
                $ip = $request->ip();
                $ua = $request->userAgent();
            } else {
                $ip = request()->ip() ?? null;
                $ua = request()->userAgent() ?? null;
            }

            return static::create([
                'user_id' => $userId,
                'action' => $action,
                'details' => $details ? $details : null,
                'ip' => $ip,
                'user_agent' => $ua,
            ]);
        } catch (\Throwable $e) {
            // don't break primary flows if logging fails; consider reporting to your logger
            Log::error('Failed to write log: ' . $e->getMessage(), ['action' => $action, 'user_id' => $userId]);
            return null;
        }
    }

        /**
         * Return a non-technical, user-friendly description for this log entry.
         */
        public function readableDetails(): string
        {
            // Use explicit human message if present
            if (is_array($this->details) && !empty($this->details['message'])) {
                $base = (string) $this->details['message'];
            } else {
                $map = [
                    'login' => 'Signed in',
                    'failed_login' => 'Failed sign-in attempt',
                    'logout' => 'Signed out',
                    'password_change' => 'Password changed',
                    'profile_edit' => 'Profile updated',
                    'device_added' => 'Device remembered',
                    'device_removed' => 'Device removed',
                    'session_removed' => 'Session ended',
                    'bleep_deleted' => 'A post was deleted',
                ];

                $base = $this->action && isset($map[$this->action]) ? $map[$this->action] : 'Details available';
            }

            // Add brief non-technical context: device/browser/os and IP (if present)
            $pieces = [];

            // If controller provided friendly device string in details, use it
            if (is_array($this->details) && !empty($this->details['device'])) {
                $pieces[] = $this->details['device'];
            } else {
                // Best-effort parse from user_agent
                if (!empty($this->user_agent)) {
                    try {
                        $os = trim(strip_tags(UserAgentParser::parseOS($this->user_agent)));
                        $browser = trim(strip_tags(UserAgentParser::parseBrowser($this->user_agent)));
                        if ($browser && $os) {
                            $pieces[] = "{$browser} on {$os}";
                        } elseif ($browser) {
                            $pieces[] = $browser;
                        } elseif ($os) {
                            $pieces[] = $os;
                        }
                    } catch (\Throwable $e) {
                        // ignore UA parse failures
                    }
                }
            }

            if (!empty($this->ip)) {
                $pieces[] = "from {$this->ip}";
            }

            if (!empty($pieces)) {
                $context = implode(', ', $pieces);
                return "{$base} — {$context}";
            }

            return $base;
        }
}

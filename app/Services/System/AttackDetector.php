<?php

namespace App\Services\System;

use App\Models\Logs;
use App\Services\GeoIpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Heuristic attack detection for the admin security tab.
 *
 * Everything here is derived from data the application already stores:
 *   - failed sign-in rows in the activity log,
 *   - attack-shaped signatures in the tail of the application log,
 *   - unusually dense sessions from a single address.
 *
 * Nothing is blocked automatically; findings are signals for a human to review.
 */
class AttackDetector
{
    private const WINDOW_HOURS = 24;

    public function __construct(private LogFileReader $log, private GeoIpService $geoIp) {}

    /**
     * @return array{confirmed: int, possible: int, scanned_at: Carbon}
     */
    public function summary(): array
    {
        $findings = $this->findings();

        return [
            'confirmed' => count(array_filter($findings, fn (array $finding) => $finding['severity'] === 'confirmed')),
            'possible' => count(array_filter($findings, fn (array $finding) => $finding['severity'] === 'possible')),
            'scanned_at' => now(),
        ];
    }

    /**
     * @return array<int, array{
     *     type: string,
     *     title: string,
     *     severity: string,
     *     subject: string,
     *     count: int,
     *     last_seen: Carbon|string|null,
     *     details: string,
     *     recommendation: string,
     *     location: array<string, mixed>|null
     * }>
     */
    public function findings(): array
    {
        $findings = array_merge(
            $this->failedLoginFindings(),
            $this->logSignatureFindings(),
            $this->sessionClusterFindings(),
        );

        usort($findings, function (array $a, array $b): int {
            $rank = ['confirmed' => 0, 'possible' => 1];

            return [$rank[$a['severity']] ?? 2, -$a['count']] <=> [$rank[$b['severity']] ?? 2, -$b['count']];
        });

        return array_map(function (array $finding): array {
            $finding['location'] = $this->geoIp->locate($finding['subject']);

            return $finding;
        }, $findings);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function failedLoginFindings(): array
    {
        try {
            $rows = Logs::query()
                ->where('action', 'failed_login')
                ->where('created_at', '>=', now()->subHours(self::WINDOW_HOURS))
                ->whereNotNull('ip')
                ->selectRaw('ip, COUNT(*) as attempts, COUNT(DISTINCT user_id) as accounts, MAX(created_at) as last_seen')
                ->groupBy('ip')
                ->havingRaw('COUNT(*) >= 5')
                ->orderByDesc('attempts')
                ->limit(25)
                ->get();
        } catch (Throwable) {
            return [];
        }

        return $rows->map(function ($row): array {
            $confirmed = (int) $row->attempts >= 15;
            $accounts = (int) $row->accounts;

            return [
                'type' => 'failed_logins',
                'title' => 'Repeated failed sign-ins',
                'severity' => $confirmed ? 'confirmed' : 'possible',
                'subject' => (string) $row->ip,
                'count' => (int) $row->attempts,
                'last_seen' => $row->last_seen ? Carbon::parse($row->last_seen) : null,
                'details' => $row->attempts.' failed attempts across '.$accounts
                    .' '.($accounts === 1 ? 'account' : 'accounts').' in the last '.self::WINDOW_HOURS.' hours.',
                'recommendation' => 'Review the address, block it if it is not legitimate, and confirm the targeted accounts use strong passwords.',
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function logSignatureFindings(): array
    {
        $signatures = [
            'sql_injection' => [
                'title' => 'SQL error signatures',
                'needle' => 'SQLSTATE[',
                'recommendation' => 'Inspect the surrounding requests; repeated SQL errors can indicate injection probing.',
            ],
            'csrf' => [
                'title' => 'CSRF token mismatches',
                'needle' => 'CSRF token mismatch',
                'recommendation' => 'Confirm the traffic is not a cross-site forgery attempt from a third-party origin.',
            ],
            'rate_limit' => [
                'title' => 'Rate limit rejections',
                'needle' => 'Too Many Attempts',
                'recommendation' => 'Check whether the source is a runaway client or deliberate request flooding.',
            ],
            'unauthenticated' => [
                'title' => 'Unauthenticated API probes',
                'needle' => 'Unauthenticated',
                'recommendation' => 'Verify the source is not enumerating protected endpoints.',
            ],
            'invalid_signature' => [
                'title' => 'Invalid signed URLs',
                'needle' => 'Invalid signature',
                'recommendation' => 'Look for tampered signed links or automated guessing against signed routes.',
            ],
        ];

        $entries = $this->log->entries(500);
        $cutoff = now()->subHours(self::WINDOW_HOURS);
        $matches = [];

        foreach ($entries as $entry) {
            $timestamp = $this->parseTimestamp($entry['timestamp']);

            if ($timestamp === null || $timestamp->lt($cutoff)) {
                continue;
            }

            foreach ($signatures as $key => $signature) {
                if (! str_contains($entry['raw'], $signature['needle'])) {
                    continue;
                }

                $matches[$key] ??= ['count' => 0, 'last_seen' => $timestamp];
                $matches[$key]['count']++;
                $matches[$key]['last_seen'] = $timestamp->gt($matches[$key]['last_seen']) ? $timestamp : $matches[$key]['last_seen'];
            }
        }

        $findings = [];

        foreach ($matches as $key => $match) {
            if ($match['count'] < 3) {
                continue;
            }

            $findings[] = [
                'type' => $key,
                'title' => $signatures[$key]['title'],
                'severity' => $match['count'] >= 10 ? 'confirmed' : 'possible',
                'subject' => 'Application log',
                'count' => $match['count'],
                'last_seen' => $match['last_seen'],
                'details' => $match['count'].' matching entries in the last '.self::WINDOW_HOURS.' hours.',
                'recommendation' => $signatures[$key]['recommendation'],
            ];
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sessionClusterFindings(): array
    {
        try {
            $rows = DB::table('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subHours(self::WINDOW_HOURS)->timestamp)
                ->whereNotNull('ip_address')
                ->selectRaw('ip_address, COUNT(*) as sessions, COUNT(DISTINCT user_id) as accounts, MAX(last_activity) as last_seen')
                ->groupBy('ip_address')
                ->havingRaw('COUNT(*) >= 8')
                ->orderByDesc('sessions')
                ->limit(25)
                ->get();
        } catch (Throwable) {
            return [];
        }

        return $rows->map(function ($row): array {
            return [
                'type' => 'session_cluster',
                'title' => 'Dense session cluster',
                'severity' => (int) $row->sessions >= 20 ? 'confirmed' : 'possible',
                'subject' => (string) $row->ip_address,
                'count' => (int) $row->sessions,
                'last_seen' => Carbon::createFromTimestamp((int) $row->last_seen),
                'details' => $row->sessions.' active sessions for '.$row->accounts
                    .' '.($row->accounts === 1 ? 'account' : 'accounts').' from one address.',
                'recommendation' => 'Confirm this is a shared network (office, school, carrier NAT) rather than session hijacking.',
            ];
        })->all();
    }

    private function parseTimestamp(?string $timestamp): ?Carbon
    {
        if (! $timestamp) {
            return null;
        }

        try {
            return Carbon::parse($timestamp);
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Services\Bulletin;

use App\Helpers\AnonymousName;
use Illuminate\Http\Request;

/**
 * Owns the anonymous identity and privacy-safe fingerprints for the public
 * bulletin board. The browser never supplies an identity we trust.
 */
class BulletinVisitor
{
    private const SESSION_KEY = 'bulletin_identity';

    public function identity(Request $request): string
    {
        $session = $request->session();
        $identity = $session->get(self::SESSION_KEY);

        if (is_string($identity) && preg_match('/^[A-Z][a-z]+[A-Z][a-z]+\d{1,3}$/', $identity) === 1) {
            return $identity;
        }

        $identity = AnonymousName::randomBulletinIdentity();
        $session->put(self::SESSION_KEY, $identity);

        return $identity;
    }

    /**
     * Stable per-session fingerprint used for reactions and reports.
     */
    public function hash(Request $request): string
    {
        return hash_hmac(
            'sha256',
            'bulletin-visitor|'.$request->session()->getId(),
            (string) config('app.key'),
        );
    }

    /**
     * HMAC of the request IP. Never persisted; only used for rate-limit keys.
     */
    public function ipHash(Request $request): string
    {
        return hash_hmac(
            'sha256',
            'bulletin-ip|'.($request->ip() ?? 'unknown'),
            (string) config('app.key'),
        );
    }
}

<?php

namespace App\Traits;

use App\Helpers\AnonymousName;
use App\Models\Bleep;
use App\Models\Comments;

trait HasAnonymousName
{
    /**
     * Deterministic anonymous display name (viewer-specific).
     * Uses user_id + viewer seed so different viewers get different names,
     * but the same viewer sees the same name for the same user across all their content.
     */
    public function anonymousDisplayNameFor(string|int $viewerSeed): string
    {
        $firstParts = AnonymousName::firstParts();
        $secondParts = AnonymousName::secondParts();

        // Get the user_id for both Bleep and Comments models
        // This ensures the same user gets the same anonymous name everywhere
        if ($this instanceof Comments) {
            $userId = $this->user_id;
        } elseif ($this instanceof Bleep) {
            $userId = $this->user_id;
        } else {
            // Fallback for any other model
            $userId = $this->id;
        }

        // deterministic hash from user_id + viewer seed
        $hash = md5((string) $userId.'|'.(string) $viewerSeed);

        $firstIndex = hexdec(substr($hash, 0, 8)) % count($firstParts);
        $secondIndex = hexdec(substr($hash, 8, 8)) % count($secondParts);

        return ucwords($firstParts[$firstIndex].' '.$secondParts[$secondIndex]);
    }

    /**
     * Generate a lowercase random username such as "littlgold128".
     *
     * Delegates to the shared display-name generator so usernames and
     * anonymous display names stay consistent with the same word pools.
     */
    public static function generateRandomUsername(): string
    {
        return strtolower(AnonymousName::randomDisplayName());
    }
}

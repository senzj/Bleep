<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServePublicAssetsDuringMaintenance
{
    /**
     * Serve real public files while the site is offline.
     *
     * This runs globally, before routing, because a stock Apache host serves
     * static files without touching PHP. Hosts that funnel every request
     * through index.php would otherwise answer the maintenance page's own
     * CSS, JS and meme image with the 503 HTML, which makes the page render
     * unstyled with a broken image.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.maintenance_mode.enabled')) {
            return $next($request);
        }

        if ($path = $this->existingPublicFile($request)) {
            return response()->file($path);
        }

        return $next($request);
    }

    /**
     * The real path of a requested public file, or null when it does not exist.
     */
    private function existingPublicFile(Request $request): ?string
    {
        $relative = ltrim($request->path(), '/');

        if ($relative === '' || str_contains($relative, '..') || pathinfo($relative, PATHINFO_EXTENSION) === '') {
            return null;
        }

        $root = realpath(public_path());
        $path = realpath(public_path($relative));

        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($path) ? $path : null;
    }
}

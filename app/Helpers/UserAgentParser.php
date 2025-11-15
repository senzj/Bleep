<?php

namespace App\Helpers;

class UserAgentParser
{
    public static function parse(?string $ua): string
    {
        if (!$ua) {
            return '<span class="inline-flex items-center gap-1.5"><i data-lucide="help-circle" class="w-3.5 h-3.5"></i>Unknown Device</span>';
        }

        return sprintf(
            '%s <span class="opacity-50">·</span> %s',
            self::parseOS($ua),
            self::parseBrowser($ua)
        );
    }

    public static function parseOS(?string $ua): string
    {
        $os = match (true) {
            (bool) preg_match('/Windows NT 10/i', $ua) => ['Windows', 'monitor'],
            (bool) preg_match('/Windows/i', $ua) => ['Windows', 'monitor'],
            (bool) preg_match('/Mac OS X/i', $ua) => ['macOS', 'laptop'],
            (bool) preg_match('/Android/i', $ua) => ['Android', 'smartphone'],
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => ['iOS', 'smartphone'],
            (bool) preg_match('/Linux/i', $ua) => ['Linux', 'terminal'],
            default => ['Unknown OS', 'help-circle'],
        };
        return sprintf('<span class="inline-flex items-center gap-1.5"><i data-lucide="%s" class="w-3.5 h-3.5"></i>%s</span>', $os[1], $os[0]);
    }

    public static function parseBrowser(?string $ua): string
    {
        $browser = match (true) {
            (bool) preg_match('/Edg\//i', $ua) => ['Edge', 'globe'],
            (bool) preg_match('/Chrome\//i', $ua) => ['Chrome', 'chrome'],
            (bool) preg_match('/Safari\//i', $ua) && !preg_match('/Chrome/i', $ua) => ['Safari', 'compass'],
            (bool) preg_match('/Firefox\//i', $ua) => ['Firefox', 'flame'],
            (bool) preg_match('/Opera|OPR\//i', $ua) => ['Opera', 'music'],
            default => ['Unknown Browser', 'help-circle'],
        };
        return sprintf('<span class="inline-flex items-center gap-1.5"><i data-lucide="%s" class="w-3.5 h-3.5"></i>%s</span>', $browser[1], $browser[0]);
    }
}

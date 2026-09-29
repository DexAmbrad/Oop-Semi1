<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    // Path rather than absolute URL so the frontend keeps serving media through
    // its own origin and dev proxy, whatever APP_URL happens to be.
    public static function public(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $segments = parse_url((string) Storage::disk('public')->url($path), PHP_URL_PATH);

        if (! $segments) {
            return null;
        }

        return '/'.ltrim($segments, '/');
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Expiring links to private files (student photos, documents), so <img> and
 * download links work without a bearer token: the signature is the
 * credential, and it's only handed to callers allowed to see the record.
 */
final class SignedFileUrl
{
    /**
     * Valid for 1–2 hours and identical within the hour, so browsers can
     * cache the file. Signed relative to the path (any host/port), then made
     * absolute on the current request's host.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function to(string $routeName, array $parameters): string
    {
        $expires = now()->addHours(2)->startOfHour();

        return url(URL::temporarySignedRoute($routeName, $expires, $parameters, absolute: false));
    }
}

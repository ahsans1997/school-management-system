<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton school configuration. Always access via current(), which is
 * served from cache and busted on every write (spec §60).
 */
class SchoolSetting extends Model
{
    public const CACHE_KEY = 'school.settings';

    /** Keys inside each secret group that are never returned to clients. */
    public const SECRET_KEYS = [
        'email_settings' => ['password'],
        'sms_settings' => ['api_key', 'api_secret', 'token', 'password'],
        'payment_settings' => ['secret_key', 'api_key', 'app_secret', 'password'],
    ];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'established_year' => 'integer',
            'socials' => 'array',
            'email_settings' => 'encrypted:array',
            'sms_settings' => 'encrypted:array',
            'payment_settings' => 'encrypted:array',
        ];
    }

    /** The singleton settings row, cached until the next write. */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(self::CACHE_KEY, function () {
            $settings = static::query()->firstOrCreate([]);

            if ($settings->wasRecentlyCreated) {
                // An insert doesn't hydrate DB-level column defaults into the
                // model, so reload them — otherwise nulls would be cached.
                $settings->refresh();
            }

            // Cache raw scalar attributes, never the model object: the cache
            // refuses to unserialize classes (config cache.serializable_classes
            // = false, an anti gadget-chain hardening) and plain data is safer.
            return $settings->getAttributes();
        });

        // Rehydrate as an existing row. Casts (incl. decryption) apply on
        // access, and wasRecentlyCreated stays false so reads never return 201.
        return (new self)->newFromBuilder($attributes);
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

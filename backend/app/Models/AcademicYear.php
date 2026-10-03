<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An academic year/session. Exactly one is current once any exist: the DB
 * unique index on `current_marker` allows at most one, and the actions keep
 * at least one (first year auto-current; the current year can't be deleted).
 */
class AcademicYear extends Model
{
    public const CURRENT_CACHE_KEY = 'academic_year.current';

    /** `current_marker` is a generated column — never mass-assigned or written. */
    protected $guarded = ['id', 'current_marker'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * The current year, cached until the next change. Caches raw attributes
     * (never the model object — see SchoolSetting::current()).
     */
    public static function current(): ?self
    {
        $attributes = Cache::rememberForever(
            self::CURRENT_CACHE_KEY,
            fn () => static::query()->where('is_current', true)->first()?->getAttributes(),
        );

        return $attributes ? (new static)->newFromBuilder($attributes) : null;
    }

    public static function forgetCurrentCache(): void
    {
        Cache::forget(self::CURRENT_CACHE_KEY);
    }
}

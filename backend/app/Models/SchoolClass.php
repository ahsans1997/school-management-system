<?php

namespace App\Models;

use App\Enums\Medium;
use App\Enums\Shift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class/grade (e.g. "Class 6"). Named SchoolClass because `class` is a PHP
 * keyword. Shared across academic years; the year is pinned at enrollment.
 */
class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numeric_level' => 'integer',
            'shift' => Shift::class,
            'medium' => Medium::class,
        ];
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }
}

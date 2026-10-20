<?php

namespace App\Models;

use App\Enums\FeeCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of fee (tuition, transport, exam …). Deactivated rather than
 * deleted once used: structures and invoice lines point at it (RESTRICT).
 */
class FeeType extends Model
{
    protected $fillable = ['name', 'code', 'category', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FeeCategory::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FeeStructure, $this>
     */
    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Grade;
use App\Models\GradeScale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradeScale */
class GradeScaleResource extends JsonResource
{
    /**
     * Bands come highest first. `below_percent` is where the band ends (the
     * next band's cut-off), or null for the top band, which runs to 100.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'exams_count' => $this->whenCounted('exams'),
            'grades' => $this->whenLoaded('grades', function () {
                $upper = null;

                return $this->grades->map(function (Grade $grade) use (&$upper) {
                    $row = [
                        'id' => $grade->id,
                        'name' => $grade->name,
                        'grade_point' => (float) $grade->grade_point,
                        'min_percent' => (float) $grade->min_percent,
                        'below_percent' => $upper,
                    ];
                    $upper = (float) $grade->min_percent;

                    return $row;
                })->all();
            }),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

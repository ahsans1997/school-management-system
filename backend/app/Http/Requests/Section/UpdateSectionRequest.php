<?php

namespace App\Http\Requests\Section;

use App\Enums\EnrollmentStatus;
use App\Models\Section;
use App\Models\StudentEnrollment;
use Illuminate\Validation\Validator;

class UpdateSectionRequest extends SectionRequest
{
    /**
     * Capacity can't drop below the active students already enrolled in the
     * section for any year (archived students don't count).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $capacity = $this->integer('capacity');
            if ($validator->errors()->has('capacity')) {
                return;
            }

            $busiest = StudentEnrollment::query()
                ->with('academicYear')
                ->selectRaw('academic_year_id, count(*) as taken')
                ->where('section_id', $this->ignoredSection()->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->whereHas('student', fn ($q) => $q->withoutTrashed())
                ->groupBy('academic_year_id')
                ->orderByDesc('taken')
                ->first();

            if ($busiest !== null && $busiest->taken > $capacity) {
                $validator->errors()->add(
                    'capacity',
                    "{$busiest->taken} students are enrolled in this section for {$busiest->academicYear->name}; capacity can't be lower.",
                );
            }
        }];
    }

    protected function classId(): int
    {
        return $this->ignoredSection()->class_id;
    }

    protected function ignoredSection(): Section
    {
        return $this->route('section');
    }
}

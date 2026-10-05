<?php

namespace App\Services\Exam;

use App\Models\Grade;
use App\Models\GradeScale;
use App\Support\GuardedDelete;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and edits grade scales. Bands are stored as cut-offs (a band runs
 * from its min_percent up to the next band's), so they can't overlap or
 * leave gaps. This service also keeps one band at 0 % so every mark gets a
 * grade, and stops grade points rising as the cut-off falls.
 */
class GradeScaleService
{
    /**
     * @param  array{name: string, grades: list<array<string, mixed>>}  $input
     */
    public function save(?GradeScale $scale, array $input): GradeScale
    {
        $bands = array_map(fn (array $g) => [
            'id' => isset($g['id']) ? (int) $g['id'] : null,
            'name' => trim((string) $g['name']),
            'grade_point' => round((float) $g['grade_point'], 2),
            'min_percent' => round((float) $g['min_percent'], 2),
        ], array_values($input['grades']));

        $this->validate($scale, $bands);

        return DB::transaction(function () use ($scale, $input, $bands) {
            if ($scale === null) {
                $scale = GradeScale::create(['name' => trim($input['name'])]);
            } else {
                GradeScale::query()->whereKey($scale->id)->lockForUpdate()->first();
                $scale->update(['name' => trim($input['name'])]);
            }

            $keep = array_values(array_filter(array_column($bands, 'id')));
            foreach ($scale->grades()->whereNotIn('id', $keep)->get() as $removed) {
                GuardedDelete::delete($removed, "Grade {$removed->name} is used by results and can't be removed.");
            }

            // Kept bands may swap names or cut-offs, which would trip the
            // unique indexes mid-update, so park them on placeholders first.
            foreach ($keep as $i => $id) {
                Grade::query()->whereKey($id)->update(['name' => '#'.$id, 'min_percent' => -1 - $i]);
            }
            foreach ($bands as $band) {
                $values = ['name' => $band['name'], 'grade_point' => $band['grade_point'], 'min_percent' => $band['min_percent']];
                $band['id'] !== null
                    ? Grade::query()->whereKey($band['id'])->update($values)
                    : $scale->grades()->create($values);
            }
            $scale->touch();

            return $scale->load('grades');
        });
    }

    public function delete(GradeScale $scale): void
    {
        GuardedDelete::delete($scale, 'This grade scale is used by an exam and cannot be deleted.');
    }

    /**
     * @param  list<array{id: int|null, name: string, grade_point: float, min_percent: float}>  $bands
     */
    private function validate(?GradeScale $scale, array $bands): void
    {
        $errors = [];
        $own = $scale?->grades()->pluck('id')->all() ?? [];
        $names = [];
        $cutoffs = [];
        $ids = [];

        foreach ($bands as $i => $band) {
            if ($band['id'] !== null && (! in_array($band['id'], $own, true) || isset($ids[$band['id']]))) {
                $errors["grades.{$i}.id"] = "That band isn't part of this scale.";
            }
            $ids[$band['id'] ?? 'new'.$i] = true;

            $name = mb_strtolower($band['name']);
            if (isset($names[$name])) {
                $errors["grades.{$i}.name"] = "Grade {$band['name']} is listed twice.";
            }
            $names[$name] = true;

            $cutoff = (int) round($band['min_percent'] * 100);
            if (isset($cutoffs[$cutoff])) {
                $errors["grades.{$i}.min_percent"] = "Two bands start at {$band['min_percent']} %.";
            }
            $cutoffs[$cutoff] = true;
        }

        if (! isset($cutoffs[0])) {
            $errors['grades'] = 'One band must start at 0 % so every mark gets a grade.';
        }

        // Walking down from the top band, grade points may only fall.
        $ordered = $bands;
        uasort($ordered, fn ($a, $b) => $b['min_percent'] <=> $a['min_percent']);
        $above = null;
        foreach ($ordered as $i => $band) {
            if ($above !== null && $band['grade_point'] > $above['grade_point']) {
                $errors["grades.{$i}.grade_point"] ??= "{$band['name']} can't have a higher grade point than {$above['name']}, which starts above it.";
            }
            $above = $band;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

<?php

namespace App\Http\Requests\Exam;

/**
 * A whole sheet of marks. Shapes only: EnterMarks checks each row against
 * the paper (which parts it has, caps, full marks) and the roll. Any
 * `total` sent is ignored — the server computes it.
 */
class SaveMarksRequest extends MarkSheetRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'records' => ['present', 'array', 'max:500'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            ...self::valueRules('records.*.'),
        ];
    }

    /**
     * Rules for one row's values (also used by UpdateMarkRequest).
     *
     * @return array<string, list<string>>
     */
    public static function valueRules(string $prefix = ''): array
    {
        $marks = ['nullable', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'];

        return [
            "{$prefix}absent" => ['sometimes', 'boolean'],
            "{$prefix}cq" => $marks,
            "{$prefix}mcq" => $marks,
            "{$prefix}practical" => $marks,
            "{$prefix}marks" => $marks,
        ];
    }
}

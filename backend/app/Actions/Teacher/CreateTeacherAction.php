<?php

namespace App\Actions\Teacher;

use App\Actions\Person\CreatePersonAccountAction;
use App\Models\Teacher;
use App\Support\Sequence;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateTeacherAction
{
    public function __construct(private readonly CreatePersonAccountAction $createAccount) {}

    /**
     * The teacher and, optionally, a Teacher-role login — together or not at all.
     *
     * @param  array<string, mixed>  $data  validated fields plus optional `account` {email, password}
     */
    public function execute(array $data): Teacher
    {
        return DB::transaction(function () use ($data) {
            $teacher = Teacher::create([
                ...Arr::except($data, 'account'),
                'employee_id' => $data['employee_id'] ?? $this->nextEmployeeId(),
            ]);

            if (! empty($data['account'])) {
                $this->createAccount->execute($teacher, 'Teacher', $data['account']);
            } else {
                $teacher->setRelation('account', null);
            }

            return $teacher;
        });
    }

    /** "T-0001", skipping any ID already typed in by hand. */
    private function nextEmployeeId(): string
    {
        do {
            $id = sprintf('T-%04d', Sequence::next('teacher_employee_id'));
        } while (Teacher::withTrashed()->where('employee_id', $id)->exists());

        return $id;
    }
}

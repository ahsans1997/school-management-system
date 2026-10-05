<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A whole sheet: every student on the section's roll with a status.
 * POST /attendance/bulk sends section + date; PUT /attendance/{id} only
 * the records (the sheet fixes section and date).
 */
class SaveAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission by middleware; section scope by TeacherScope in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('attendance') === null;

        return [
            'section_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('sections', 'id')],
            'date' => [$creating ? 'required' : 'prohibited', 'date_format:Y-m-d'],
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}

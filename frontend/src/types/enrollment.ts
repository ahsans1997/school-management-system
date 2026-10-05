import type { Paginated } from "@/types/api";

export type EnrollmentStatus =
  "active" | "promoted" | "transferred" | "left" | "graduated";

interface Ref {
  id: number;
  name: string;
}

/** A student's place for one academic year. The class always follows the section. */
export interface Enrollment {
  id: number;
  student?: Ref & { student_id: string; archived: boolean };
  academic_year?: Ref & { is_current: boolean };
  class?: Ref;
  section?: Ref;
  roll: number;
  status: EnrollmentStatus;
  /** Set when the status isn't active. */
  ended_on: string | null;
  exit_reason: string | null;
  created_at: string | null;
}

export interface EnrollmentPage extends Paginated<Enrollment> {
  /** The year the list shows (defaults to the current one). */
  academic_year_id: number | null;
  /** With a section filter: that section's seats for the year. */
  seats?: { capacity: number; taken: number };
}

export interface EnrollmentChange {
  section_id: number;
  /** Blank → keep (or the next roll when moving section). */
  roll: number | null;
  status: EnrollmentStatus;
  ended_on?: string | null;
  exit_reason?: string | null;
}

/** Mirrors App\Enums\EnrollmentStatus. */
export const ENROLLMENT_STATUSES: { value: EnrollmentStatus; label: string }[] =
  [
    { value: "active", label: "Active" },
    { value: "promoted", label: "Promoted" },
    { value: "transferred", label: "Transferred" },
    { value: "left", label: "Left" },
    { value: "graduated", label: "Graduated" },
  ];

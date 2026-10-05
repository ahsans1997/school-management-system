export type Weekday = "sat" | "sun" | "mon" | "tue" | "wed" | "thu" | "fri";

/** Mirrors App\Enums\Weekday, in school-week order. */
export const WEEKDAYS: { value: Weekday; label: string; short: string }[] = [
  { value: "sat", label: "Saturday", short: "Sat" },
  { value: "sun", label: "Sunday", short: "Sun" },
  { value: "mon", label: "Monday", short: "Mon" },
  { value: "tue", label: "Tuesday", short: "Tue" },
  { value: "wed", label: "Wednesday", short: "Wed" },
  { value: "thu", label: "Thursday", short: "Thu" },
  { value: "fri", label: "Friday", short: "Fri" },
];

interface Ref {
  id: number;
  name: string;
}

/** One period as returned by every routine view. */
export interface RoutineSlot {
  id: number;
  day: Weekday;
  period_no: number;
  /** "HH:MM" */
  start_time: string;
  end_time: string;
  /** Set for non-teaching periods (e.g. "Break"). */
  label: string | null;
  room: string | null;
  teaching_assignment_id: number | null;
  subject: Ref | null;
  teacher: Ref | null;
  class: Ref;
  section: Ref;
}

/** A subject + teacher the section's routine can use (its teaching assignments). */
export interface RoutineOption {
  teaching_assignment_id: number;
  subject: string;
  teacher: string;
}

export interface SectionRoutine {
  academic_year: Ref;
  section: { id: number; name: string; class: string };
  routine: { id: number; updated_at: string | null } | null;
  slots: RoutineSlot[];
  options: RoutineOption[];
}

export interface SlotInput {
  day: Weekday;
  period_no: number;
  start_time: string;
  end_time: string;
  teaching_assignment_id: number | null;
  label: string | null;
  room: string | null;
}

/** GET /routines/me — shaped by who's asking. */
export type MyRoutine =
  | {
      type: "teacher";
      academic_year: Ref;
      teacher: Ref | null;
      slots: RoutineSlot[];
    }
  | {
      type: "student" | "parent";
      academic_year: Ref;
      weeks: {
        student: Ref;
        section: { id: number; name: string; class: string } | null;
        slots: RoutineSlot[];
      }[];
    }
  | { type: "none"; academic_year: Ref };

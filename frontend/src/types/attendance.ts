export type AttendanceStatus = "present" | "absent" | "late" | "leave";

/** Mirrors App\Enums\AttendanceStatus. `short` is the grid code. */
export const ATTENDANCE_STATUSES: {
  value: AttendanceStatus;
  label: string;
  short: string;
}[] = [
  { value: "present", label: "Present", short: "P" },
  { value: "absent", label: "Absent", short: "A" },
  { value: "late", label: "Late", short: "L" },
  { value: "leave", label: "Leave", short: "Lv" },
];

/** A section the caller may take attendance for (teachers: their own only). */
export interface AttendanceSection {
  id: number;
  name: string;
  class: { id: number; name: string };
  /** Active students this year. */
  students: number;
  marked_today: boolean;
}

export interface SheetRow {
  student_id: number;
  name: string;
  /** Null for a row kept from before (e.g. student archived since). */
  roll: number | null;
  status: AttendanceStatus | null;
  remark: string | null;
}

export interface AttendanceSheet {
  section: { id: number; name: string; class: string };
  date: string;
  academic_year: { id: number; name: string };
  /** Null until the day's sheet is saved. */
  attendance: {
    id: number;
    taken_by: string | null;
    updated_by: string | null;
    updated_at: string | null;
  } | null;
  students: SheetRow[];
}

export interface AttendanceSummary {
  present: number;
  absent: number;
  late: number;
  leave: number;
  marked: number;
  /** (present + late) / marked × 100; null when nothing was marked. */
  percentage: number | null;
}

export interface MonthlyAttendance {
  section: { id: number; name: string; class: string };
  month: string;
  /** Days that have a sheet (YYYY-MM-DD). */
  days: string[];
  students: {
    student_id: number;
    name: string;
    roll: number | null;
    statuses: Record<string, AttendanceStatus>;
    summary: AttendanceSummary;
  }[];
}

export interface StudentAttendanceReport {
  academic_year: { id: number; name: string };
  summary: AttendanceSummary;
  months: (AttendanceSummary & { month: string })[];
  exceptions: {
    date: string;
    status: AttendanceStatus;
    remark: string | null;
  }[];
}

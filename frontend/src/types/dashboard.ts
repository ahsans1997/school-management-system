import type { Audience } from "@/types/auth";
import type { MarkSheetSummary } from "@/types/marks";

export type WidgetStatus = "ready" | "pending";

export interface DashboardWidget<T = Record<string, unknown>> {
  key: string;
  title: string;
  status: WidgetStatus;
  /** Source module label, present on pending widgets. */
  module?: string;
  data: T | null;
}

export interface DashboardPayload {
  audience: Audience;
  generated_at: string;
  widgets: DashboardWidget[];
}

export interface ProfileWidgetData {
  name: string;
  email: string;
  roles: string[];
}

export interface SchoolWidgetData {
  name: string;
  logo_url: string | null;
  eiin: string | null;
  established_year: number | null;
  principal_name: string | null;
  address: string | null;
  /** Name of the current academic year, if one exists. */
  academic_year: string | null;
}

export interface UserStatsWidgetData {
  total: number;
  active: number;
  inactive: number;
  by_role: { role: string; count: number }[];
}

export interface StudentStatsWidgetData {
  total: number;
  admitted_this_month: number;
  archived: number;
  by_gender: { gender: string; label: string; count: number }[];
}

export interface ChildrenWidgetData {
  /** False when this login isn't linked to a guardian record. */
  linked: boolean;
  items: {
    id: number;
    name: string;
    student_id: string;
    relationship: string;
  }[];
}

export interface TeacherStatsWidgetData {
  total: number;
  with_login: number;
  /** No subject and no class-teacher section yet. */
  unassigned: number;
  archived: number;
}

export interface AssignedSubjectsWidgetData {
  /** False when this login isn't linked to a teacher record. */
  linked: boolean;
  subjects: { class: string; section: string; subject: string }[];
  class_teacher_of: { class: string; section: string }[];
}

export interface AttendanceSummaryData {
  present: number;
  absent: number;
  late: number;
  leave: number;
  marked: number;
  percentage: number | null;
}

export interface AttendanceTodayWidgetData extends AttendanceSummaryData {
  date: string;
  sections_total: number;
  sections_marked: number;
}

export interface AttendanceTasksWidgetData {
  linked: boolean;
  sections: {
    section_id: number;
    class: string;
    section: string;
    marked: boolean;
  }[];
}

/** A teacher's mark sheets still to finish (open, not submitted). */
export interface PendingMarksWidgetData {
  linked: boolean;
  /** All pending sheets; `sheets` lists the first few. */
  total: number;
  sheets: (MarkSheetSummary & { exam: { id: number; name: string } })[];
}

/** `my_results` (student) / `child_results` (parent): published only. */
export interface PublishedResultsWidgetData {
  linked: boolean;
  students: {
    student: { id: number; name: string };
    results: {
      id: number;
      exam: { id: number; name: string };
      class: string;
      section: string;
      gpa: number;
      grade: string;
      passed: boolean;
      position: number | null;
      published_at: string | null;
    }[];
  }[];
}

export interface ChildAttendanceWidgetData {
  linked: boolean;
  academic_year?: string;
  children: (AttendanceSummaryData & { name: string })[];
}

export interface MyAttendanceWidgetData {
  linked: boolean;
  academic_year?: string;
  summary: AttendanceSummaryData | null;
}

/** Today's periods (teacher: theirs; student: their section's). */
export interface TodaysPeriodsWidgetData {
  linked: boolean;
  day: string;
  periods: {
    id: number;
    start_time: string;
    end_time: string;
    label: string | null;
    room: string | null;
    subject: { name: string } | null;
    teacher: { name: string } | null;
    class: { name: string };
    section: { name: string };
  }[];
}

/** Upcoming exams: admins see all (with counts), portals only their own. */
export interface UpcomingExamsWidgetData {
  exams: {
    id: number;
    name: string;
    type: string;
    start_date: string;
    end_date: string | null;
    /** Admin only. */
    papers?: number;
    classes?: number;
  }[];
}

export interface RecentUsersWidgetData {
  items: {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_active: boolean;
    created_at: string | null;
  }[];
}

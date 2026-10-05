import { authedFetch } from "@/services/authService";
import type {
  AttendanceSection,
  AttendanceSheet,
  AttendanceStatus,
  MonthlyAttendance,
  StudentAttendanceReport,
} from "@/types/attendance";

/** Sections the caller may take attendance for this year (teachers: their own). */
export function listSections(): Promise<AttendanceSection[]> {
  return authedFetch<{ data: AttendanceSection[] }>(
    "/attendance/sections",
  ).then((r) => r.data);
}

/** The day's roll with any statuses already saved. */
export function getSheet(
  sectionId: number,
  date: string,
): Promise<AttendanceSheet> {
  return authedFetch<{ data: AttendanceSheet }>(
    `/attendance/sheet?section_id=${sectionId}&date=${date}`,
  ).then((r) => r.data);
}

export interface SheetRecord {
  student_id: number;
  status: AttendanceStatus;
  remark: string | null;
}

/** Save the whole section for a day (creates or updates the sheet). */
export function saveSheet(
  sectionId: number,
  date: string,
  records: SheetRecord[],
): Promise<unknown> {
  return authedFetch<unknown>("/attendance/bulk", {
    method: "POST",
    body: {
      section_id: sectionId,
      date,
      records: records.map((r) => ({ ...r })),
    },
  });
}

export function getMonthly(
  sectionId: number,
  month: string,
): Promise<MonthlyAttendance> {
  return authedFetch<{ data: MonthlyAttendance }>(
    `/attendance/monthly?section_id=${sectionId}&month=${month}`,
  ).then((r) => r.data);
}

export function getStudentReport(
  studentId: number,
): Promise<StudentAttendanceReport> {
  return authedFetch<{ data: StudentAttendanceReport }>(
    `/students/${studentId}/attendance`,
  ).then((r) => r.data);
}

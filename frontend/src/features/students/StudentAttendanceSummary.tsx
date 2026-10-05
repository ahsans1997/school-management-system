"use client";

import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import { formatDay } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as attendanceService from "@/services/attendanceService";
import {
  ATTENDANCE_STATUSES,
  type StudentAttendanceReport,
} from "@/types/attendance";

/** This year's attendance for one student: totals, % and the days they weren't present. */
export function StudentAttendanceSummary({ studentId }: { studentId: number }) {
  const [report, setReport] = useState<StudentAttendanceReport | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    attendanceService
      .getStudentReport(studentId)
      .then((r) => {
        if (active) setReport(r);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [studentId]);

  return (
    <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
      <h2 className="mb-3 text-sm font-semibold">
        Attendance{report ? ` · ${report.academic_year.name}` : ""}
      </h2>
      {error ? (
        <p className="text-sm text-gray-500">{error}</p>
      ) : !report ? (
        <p className="text-sm text-gray-500">Loading…</p>
      ) : report.summary.marked === 0 ? (
        <p className="text-sm text-gray-500">No attendance taken yet.</p>
      ) : (
        <div className="space-y-2 text-sm">
          <p>
            <span className="text-lg font-semibold tabular-nums">
              {report.summary.percentage}%
            </span>{" "}
            <span className="text-gray-500 tabular-nums">
              · {report.summary.present} present, {report.summary.late} late,{" "}
              {report.summary.absent} absent, {report.summary.leave} leave of{" "}
              {report.summary.marked}{" "}
              {report.summary.marked === 1 ? "day" : "days"}
            </span>
          </p>
          {report.exceptions.length > 0 && (
            <details>
              <summary className="cursor-pointer text-gray-500">
                Days not present ({report.exceptions.length})
              </summary>
              <ul className="mt-1 divide-y divide-gray-100 dark:divide-gray-800">
                {report.exceptions.map((x) => (
                  <li key={x.date} className="flex justify-between py-1">
                    <span>{formatDay(x.date)}</span>
                    <span className="text-gray-500">
                      {labelOf(ATTENDANCE_STATUSES, x.status)}
                      {x.remark && ` · ${x.remark}`}
                    </span>
                  </li>
                ))}
              </ul>
            </details>
          )}
        </div>
      )}
    </section>
  );
}

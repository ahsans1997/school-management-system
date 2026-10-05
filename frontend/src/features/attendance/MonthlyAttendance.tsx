"use client";

import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import { localToday } from "@/lib/format";
import * as attendanceService from "@/services/attendanceService";
import {
  ATTENDANCE_STATUSES,
  type AttendanceSection,
  type MonthlyAttendance as Monthly,
} from "@/types/attendance";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

const CODE: Record<string, string> = Object.fromEntries(
  ATTENDANCE_STATUSES.map((s) => [s.value, s.short]),
);
const CODE_STYLE: Record<string, string> = {
  absent: "text-red-600 font-medium",
  late: "text-amber-600",
  leave: "text-sky-600",
};

/** A section's month: one column per day taken, percentage per student (computed by the API). */
export function MonthlyAttendance({
  sections,
}: {
  sections: AttendanceSection[];
}) {
  const [sectionId, setSectionId] = useState(
    sections.length === 1 ? String(sections[0].id) : "",
  );
  const [month, setMonth] = useState(localToday().slice(0, 7));
  const [data, setData] = useState<Monthly | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    if (!sectionId || !month) return;
    let active = true;
    attendanceService
      .getMonthly(Number(sectionId), month)
      .then((res) => {
        if (active) {
          setError(null);
          setData(res);
        }
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [sectionId, month]);

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <select
          aria-label="Section"
          value={sectionId}
          onChange={(e) => setSectionId(e.target.value)}
          className={inputClass}
        >
          <option value="">Choose a section…</option>
          {sections.map((s) => (
            <option key={s.id} value={s.id}>
              {s.class.name} {s.name}
            </option>
          ))}
        </select>
        <input
          type="month"
          aria-label="Month"
          value={month}
          onChange={(e) => setMonth(e.target.value)}
          className={inputClass}
        />
      </div>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}

      {!sectionId || !data ? (
        <p className="text-sm text-gray-500">Choose a section and month.</p>
      ) : data.students.length === 0 ? (
        <p className="text-sm text-gray-500">No students for this month.</p>
      ) : (
        <>
          <p className="mb-2 text-xs text-gray-500">
            {data.days.length} day(s) taken. P present · A absent · L late · Lv
            leave. Percentage = (present + late) ÷ days marked.
          </p>
          <div className="overflow-x-auto">
            <table className="text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                  <th className="sticky left-0 bg-gray-50 py-2 pr-3 font-medium dark:bg-gray-950">
                    Student
                  </th>
                  {data.days.map((d) => (
                    <th
                      key={d}
                      className="px-1.5 py-2 text-center font-medium tabular-nums"
                    >
                      {Number(d.slice(8))}
                    </th>
                  ))}
                  <th className="px-2 py-2 text-right font-medium">%</th>
                </tr>
              </thead>
              <tbody>
                {data.students.map((s) => (
                  <tr
                    key={s.student_id}
                    className="border-b border-gray-100 dark:border-gray-900"
                  >
                    <td className="sticky left-0 bg-gray-50 py-1.5 pr-3 whitespace-nowrap dark:bg-gray-950">
                      <span className="mr-1 text-xs text-gray-500 tabular-nums">
                        {s.roll ?? "—"}
                      </span>
                      {s.name}
                    </td>
                    {data.days.map((d) => (
                      <td
                        key={d}
                        className={`px-1.5 py-1.5 text-center text-xs ${CODE_STYLE[s.statuses[d]] ?? ""}`}
                      >
                        {s.statuses[d] ? CODE[s.statuses[d]] : "·"}
                      </td>
                    ))}
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {s.summary.percentage === null
                        ? "—"
                        : `${s.summary.percentage}%`}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

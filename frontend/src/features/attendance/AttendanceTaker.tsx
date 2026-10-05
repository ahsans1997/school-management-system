"use client";

import { useCallback, useEffect, useState } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatDate, localToday } from "@/lib/format";
import * as attendanceService from "@/services/attendanceService";
import {
  ATTENDANCE_STATUSES,
  type AttendanceSection,
  type AttendanceSheet,
  type AttendanceStatus,
} from "@/types/attendance";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

const STATUS_STYLE: Record<AttendanceStatus, string> = {
  present: "bg-green-600 text-white border-green-600",
  absent: "bg-red-600 text-white border-red-600",
  late: "bg-amber-500 text-white border-amber-500",
  leave: "bg-sky-600 text-white border-sky-600",
};

type Marks = Record<number, { status: AttendanceStatus; remark: string }>;

/** Take or edit a section's attendance for a day, whole roll in one save. */
export function AttendanceTaker({
  sections,
  initialSectionId,
}: {
  sections: AttendanceSection[];
  initialSectionId?: number;
}) {
  const { can } = useAuth();
  const [sectionId, setSectionId] = useState(
    initialSectionId && sections.some((s) => s.id === initialSectionId)
      ? String(initialSectionId)
      : sections.length === 1
        ? String(sections[0].id)
        : "",
  );
  const [date, setDate] = useState(localToday());
  const [sheet, setSheet] = useState<AttendanceSheet | null>(null);
  const [marks, setMarks] = useState<Marks>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const applySheet = useCallback((s: AttendanceSheet) => {
    setSheet(s);
    // A new sheet starts everyone as present; teachers change the exceptions.
    setMarks(
      Object.fromEntries(
        s.students.map((row) => [
          row.student_id,
          { status: row.status ?? "present", remark: row.remark ?? "" },
        ]),
      ),
    );
  }, []);

  // Load the sheet whenever section or date changes: state set only in callbacks.
  useEffect(() => {
    if (!sectionId || !date) return;
    let active = true;
    attendanceService
      .getSheet(Number(sectionId), date)
      .then((s) => {
        if (active) {
          setError(null);
          applySheet(s);
        }
      })
      .catch((err) => {
        if (active) {
          setSheet(null);
          setError(errorMessage(err));
        }
      });
    return () => {
      active = false;
    };
  }, [sectionId, date, applySheet]);

  const existing = sheet?.attendance ?? null;
  const canSave = existing ? can("attendance.edit") : can("attendance.create");

  function setStatus(studentId: number, status: AttendanceStatus) {
    setMarks((m) => ({ ...m, [studentId]: { ...m[studentId], status } }));
  }

  function setAll(status: AttendanceStatus) {
    setMarks((m) =>
      Object.fromEntries(
        Object.entries(m).map(([id, mark]) => [id, { ...mark, status }]),
      ),
    );
  }

  async function save() {
    if (!sheet) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await attendanceService.saveSheet(
        Number(sectionId),
        date,
        sheet.students.map((row) => ({
          student_id: row.student_id,
          status: marks[row.student_id].status,
          remark: marks[row.student_id].remark.trim() || null,
        })),
      );
      applySheet(await attendanceService.getSheet(Number(sectionId), date));
      setNotice(
        `Attendance ${existing ? "updated" : "saved"} for ${sheet.section.class} ${sheet.section.name}, ${formatDate(date)}.`,
      );
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const counts = Object.values(marks).reduce<Record<string, number>>(
    (acc, m) => ({ ...acc, [m.status]: (acc[m.status] ?? 0) + 1 }),
    {},
  );

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <select
          aria-label="Section"
          value={sectionId}
          onChange={(e) => {
            setSectionId(e.target.value);
            setNotice(null);
          }}
          className={inputClass}
        >
          <option value="">Choose a section…</option>
          {sections.map((s) => (
            <option key={s.id} value={s.id}>
              {s.class.name} {s.name}
              {s.marked_today ? " ✓" : ""}
            </option>
          ))}
        </select>
        <input
          type="date"
          aria-label="Date"
          max={localToday()}
          value={date}
          onChange={(e) => {
            setDate(e.target.value);
            setNotice(null);
          }}
          className={inputClass}
        />
      </div>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      {!sectionId ? (
        <p className="text-sm text-gray-500">
          {sections.length === 0
            ? "You have no sections to take attendance for."
            : "Choose a section to take attendance."}
        </p>
      ) : sheet === null ? null : sheet.students.length === 0 ? (
        <p className="text-sm text-gray-500">
          No students are enrolled in this section for{" "}
          {sheet.academic_year.name}.
        </p>
      ) : (
        <>
          <p className="mb-3 text-xs text-gray-500">
            {existing
              ? `Taken by ${existing.taken_by ?? "someone"}${existing.updated_by && existing.updated_by !== existing.taken_by ? `, last changed by ${existing.updated_by}` : ""}.`
              : "Not taken yet: everyone starts as Present, so change the exceptions."}
          </p>

          {canSave && (
            <div className="mb-3 flex flex-wrap gap-2 text-xs">
              <span className="self-center text-gray-500">Mark all:</span>
              {ATTENDANCE_STATUSES.map((s) => (
                <button
                  key={s.value}
                  type="button"
                  onClick={() => setAll(s.value)}
                  className="rounded border border-gray-300 px-2 py-0.5 dark:border-gray-700"
                >
                  {s.label}
                </button>
              ))}
            </div>
          )}

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                  <th className="py-2 pr-3 font-medium">Roll</th>
                  <th className="py-2 pr-3 font-medium">Student</th>
                  <th className="py-2 pr-3 font-medium">Status</th>
                  <th className="py-2 font-medium">Remark</th>
                </tr>
              </thead>
              <tbody>
                {sheet.students.map((row) => {
                  const mark = marks[row.student_id];
                  return (
                    <tr
                      key={row.student_id}
                      className="border-b border-gray-100 dark:border-gray-900"
                    >
                      <td className="py-2 pr-3 tabular-nums">
                        {row.roll ?? "—"}
                      </td>
                      <td className="py-2 pr-3 font-medium">{row.name}</td>
                      <td className="py-2 pr-3">
                        <div
                          role="group"
                          aria-label={`Status of ${row.name}`}
                          className="flex gap-1"
                        >
                          {ATTENDANCE_STATUSES.map((s) => (
                            <button
                              key={s.value}
                              type="button"
                              title={s.label}
                              aria-pressed={mark?.status === s.value}
                              disabled={!canSave}
                              onClick={() => setStatus(row.student_id, s.value)}
                              className={`min-w-8 rounded border px-1.5 py-0.5 text-xs ${
                                mark?.status === s.value
                                  ? STATUS_STYLE[s.value]
                                  : "border-gray-300 dark:border-gray-700"
                              }`}
                            >
                              {s.short}
                            </button>
                          ))}
                        </div>
                      </td>
                      <td className="py-2">
                        <input
                          aria-label={`Remark for ${row.name}`}
                          maxLength={255}
                          disabled={!canSave}
                          value={mark?.remark ?? ""}
                          onChange={(e) =>
                            setMarks((m) => ({
                              ...m,
                              [row.student_id]: {
                                ...m[row.student_id],
                                remark: e.target.value,
                              },
                            }))
                          }
                          className={`w-full min-w-32 ${inputClass}`}
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          <div className="mt-4 flex flex-wrap items-center gap-4">
            {canSave && (
              <button
                type="button"
                disabled={busy}
                onClick={() => void save()}
                className="rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
              >
                {busy
                  ? "Saving…"
                  : existing
                    ? "Save changes"
                    : "Save attendance"}
              </button>
            )}
            <span className="text-xs text-gray-500 tabular-nums">
              {ATTENDANCE_STATUSES.map(
                (s) => `${s.label} ${counts[s.value] ?? 0}`,
              ).join(" · ")}
            </span>
          </div>
        </>
      )}
    </div>
  );
}

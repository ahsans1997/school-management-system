"use client";

import { useEffect, useState } from "react";
import { RoutineGrid } from "@/components/RoutineGrid";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import * as classService from "@/services/classService";
import * as routineService from "@/services/routineService";
import * as sectionService from "@/services/sectionService";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";
import { RoomLookup } from "./RoomLookup";
import {
  WEEKDAYS,
  type RoutineSlot,
  type SectionRoutine,
  type SlotInput,
  type Weekday,
} from "@/types/routine";

const inputClass =
  "rounded-md border border-gray-300 px-1.5 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

/** "" option value = a labelled, non-teaching period. */
interface Row {
  key: number;
  day: Weekday;
  period_no: string;
  start_time: string;
  end_time: string;
  assignment: string;
  label: string;
  room: string;
}

let nextKey = 1;

function toRows(slots: RoutineSlot[]): Row[] {
  return slots.map((s) => ({
    key: nextKey++,
    day: s.day,
    period_no: String(s.period_no),
    start_time: s.start_time,
    end_time: s.end_time,
    assignment: s.teaching_assignment_id
      ? String(s.teaching_assignment_id)
      : "",
    label: s.label ?? "",
    room: s.room ?? "",
  }));
}

function toInput(rows: Row[]): SlotInput[] {
  return rows.map((r) => ({
    day: r.day,
    period_no: Number(r.period_no),
    start_time: r.start_time,
    end_time: r.end_time,
    teaching_assignment_id: r.assignment ? Number(r.assignment) : null,
    label: r.assignment ? null : r.label.trim() || null,
    room: r.room.trim() || null,
  }));
}

/** "HH:MM" + minutes, for suggesting the next period's times. */
function addMinutes(time: string, minutes: number): string {
  const [h, m] = time.split(":").map(Number);
  const total = Math.min(h * 60 + m + minutes, 23 * 60 + 59);
  return `${String(Math.floor(total / 60)).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`;
}

/** Build a section's weekly routine; the API blocks teacher/room/overlap clashes. */
export function RoutineBuilder() {
  const { can } = useAuth();
  const canSave = can("routine.create") || can("routine.edit");
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [classId, setClassId] = useState("");
  const [sections, setSections] = useState<Section[]>([]);
  const [sectionId, setSectionId] = useState("");
  const [routine, setRoutine] = useState<SectionRoutine | null>(null);
  const [rows, setRows] = useState<Row[]>([]);
  const [rowErrors, setRowErrors] = useState<Record<number, string[]>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Class options: state set only in promise callbacks.
  useEffect(() => {
    let active = true;
    classService
      .listClasses(1, 100)
      .then((res) => {
        if (active) setClasses(res.data);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  function chooseClass(value: string) {
    setClassId(value);
    setSectionId("");
    setSections([]);
    setRoutine(null);
    setRows([]);
    if (!value) return;
    sectionService
      .listSections(Number(value), 1, 100)
      .then((res) => setSections(res.data))
      .catch((err) => setError(errorMessage(err)));
  }

  function chooseSection(value: string) {
    setSectionId(value);
    setRoutine(null);
    setRows([]);
    setRowErrors({});
    setNotice(null);
    if (!value) return;
    routineService
      .getSectionRoutine(Number(value))
      .then((r) => {
        setError(null);
        setRoutine(r);
        setRows(toRows(r.slots));
      })
      .catch((err) => setError(errorMessage(err)));
  }

  function update(key: number, patch: Partial<Row>) {
    setRows((rs) => rs.map((r) => (r.key === key ? { ...r, ...patch } : r)));
  }

  function addRow() {
    const last = rows[rows.length - 1];
    setRows((rs) => [
      ...rs,
      last
        ? {
            key: nextKey++,
            day: last.day,
            period_no: String(Number(last.period_no) + 1),
            start_time: last.end_time,
            end_time: addMinutes(last.end_time, 45),
            assignment: "",
            label: "",
            room: last.room,
          }
        : {
            key: nextKey++,
            day: "sun",
            period_no: "1",
            start_time: "08:00",
            end_time: "08:45",
            assignment: "",
            label: "",
            room: "",
          },
    ]);
  }

  async function save() {
    if (!routine) return;
    setBusy(true);
    setError(null);
    setNotice(null);
    setRowErrors({});
    try {
      const saved = await routineService.saveRoutine(
        routine.section.id,
        routine.academic_year.id,
        toInput(rows),
      );
      setRoutine(saved);
      setRows(toRows(saved.slots));
      setNotice(
        `Routine saved for ${saved.section.class} ${saved.section.name}.`,
      );
    } catch (err) {
      setError(errorMessage(err));
      // Pin errors (clashes and field errors) to their rows: keys "slots.N[.field]".
      if (err instanceof ApiRequestError && err.error.errors) {
        const byRow: Record<number, string[]> = {};
        for (const [key, messages] of Object.entries(err.error.errors)) {
          const match = /^slots\.(\d+)/.exec(key);
          if (match) {
            const index = Number(match[1]);
            byRow[index] = [...(byRow[index] ?? []), ...messages];
          }
        }
        setRowErrors(byRow);
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-6xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Class routine</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        A section&apos;s weekly periods for the current year, built from its
        teaching assignments. A teacher or room booked twice at the same time
        blocks saving.
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      <div className="mb-4 flex flex-wrap gap-2">
        <select
          aria-label="Class"
          value={classId}
          onChange={(e) => chooseClass(e.target.value)}
          className={inputClass}
        >
          <option value="">Class…</option>
          {classes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        <select
          aria-label="Section"
          value={sectionId}
          disabled={!classId}
          onChange={(e) => chooseSection(e.target.value)}
          className={inputClass}
        >
          <option value="">Section…</option>
          {sections.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
        {routine && (
          <span className="self-center text-xs text-gray-500">
            {routine.academic_year.name}
          </span>
        )}
      </div>

      {routine && (
        <>
          {canSave && (
            <section className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
              <h2 className="mb-3 text-sm font-semibold">Periods</h2>
              {routine.options.length === 0 && (
                <p className="mb-3 text-xs text-gray-500">
                  This section has no teaching assignments yet: assign subjects
                  to teachers on their profiles first. Labelled periods (e.g.
                  Break) work meanwhile.
                </p>
              )}
              {rows.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs text-gray-500">
                        <th className="pr-2 pb-1 font-medium">Day</th>
                        <th className="pr-2 pb-1 font-medium">#</th>
                        <th className="pr-2 pb-1 font-medium">Start</th>
                        <th className="pr-2 pb-1 font-medium">End</th>
                        <th className="pr-2 pb-1 font-medium">
                          Subject — teacher
                        </th>
                        <th className="pr-2 pb-1 font-medium">Room</th>
                        <th />
                      </tr>
                    </thead>
                    <tbody>
                      {rows.map((r, i) => (
                        <tr key={r.key} className="align-top">
                          <td className="py-1 pr-2">
                            <select
                              aria-label="Day"
                              value={r.day}
                              onChange={(e) =>
                                update(r.key, {
                                  day: e.target.value as Weekday,
                                })
                              }
                              className={inputClass}
                            >
                              {WEEKDAYS.map((d) => (
                                <option key={d.value} value={d.value}>
                                  {d.short}
                                </option>
                              ))}
                            </select>
                          </td>
                          <td className="py-1 pr-2">
                            <input
                              type="number"
                              aria-label="Period number"
                              min={1}
                              max={20}
                              value={r.period_no}
                              onChange={(e) =>
                                update(r.key, { period_no: e.target.value })
                              }
                              className={`w-14 ${inputClass}`}
                            />
                          </td>
                          <td className="py-1 pr-2">
                            <input
                              type="time"
                              aria-label="Start"
                              value={r.start_time}
                              onChange={(e) =>
                                update(r.key, { start_time: e.target.value })
                              }
                              className={inputClass}
                            />
                          </td>
                          <td className="py-1 pr-2">
                            <input
                              type="time"
                              aria-label="End"
                              value={r.end_time}
                              onChange={(e) =>
                                update(r.key, { end_time: e.target.value })
                              }
                              className={inputClass}
                            />
                          </td>
                          <td className="py-1 pr-2">
                            <select
                              aria-label="Subject"
                              value={r.assignment}
                              onChange={(e) =>
                                update(r.key, { assignment: e.target.value })
                              }
                              className={`max-w-56 ${inputClass}`}
                            >
                              <option value="">Not a lesson (label it)</option>
                              {routine.options.map((o) => (
                                <option
                                  key={o.teaching_assignment_id}
                                  value={o.teaching_assignment_id}
                                >
                                  {o.subject} — {o.teacher}
                                </option>
                              ))}
                            </select>
                            {!r.assignment && (
                              <input
                                aria-label="Label"
                                maxLength={50}
                                placeholder="e.g. Break"
                                value={r.label}
                                onChange={(e) =>
                                  update(r.key, { label: e.target.value })
                                }
                                className={`mt-1 block w-full ${inputClass}`}
                              />
                            )}
                            {rowErrors[i]?.map((m) => (
                              <p
                                key={m}
                                className="mt-1 max-w-72 text-xs text-red-600"
                              >
                                {m}
                              </p>
                            ))}
                          </td>
                          <td className="py-1 pr-2">
                            <input
                              aria-label="Room"
                              maxLength={50}
                              value={r.room}
                              onChange={(e) =>
                                update(r.key, { room: e.target.value })
                              }
                              className={`w-20 ${inputClass}`}
                            />
                          </td>
                          <td className="py-1">
                            <button
                              type="button"
                              aria-label="Remove period"
                              onClick={() =>
                                setRows((rs) =>
                                  rs.filter((x) => x.key !== r.key),
                                )
                              }
                              className="px-1 text-red-600"
                            >
                              ×
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
              <div className="mt-3 flex flex-wrap gap-3">
                <button
                  type="button"
                  onClick={addRow}
                  className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
                >
                  Add period
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void save()}
                  className="rounded-md bg-gray-900 px-4 py-1 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
                >
                  {busy ? "Saving…" : "Save routine"}
                </button>
              </div>
            </section>
          )}

          <h2 className="mb-2 text-sm font-semibold">Week</h2>
          <RoutineGrid slots={routine.slots} perspective="section" />
        </>
      )}

      <RoomLookup />
    </div>
  );
}

"use client";

import { useState } from "react";
import { errorMessage } from "@/lib/errors";
import * as enrollmentService from "@/services/enrollmentService";
import * as sectionService from "@/services/sectionService";
import type { AcademicYear } from "@/types/academicYear";
import type { Enrollment } from "@/types/enrollment";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";

const inputClass = "input input-sm";

interface Props {
  fromYear: AcademicYear;
  fromClass: SchoolClass;
  fromSectionId: number;
  /** The section's enrollments for the year (active ones are promotable). */
  enrollments: Enrollment[];
  years: AcademicYear[];
  classes: SchoolClass[];
  onDone: (message: string) => void;
  onCancel: () => void;
}

/** Move chosen students of this section up one class into a later year — all or nothing. */
export function PromotePanel({
  fromYear,
  fromClass,
  fromSectionId,
  enrollments,
  years,
  classes,
  onDone,
  onCancel,
}: Props) {
  const active = enrollments.filter((e) => e.status === "active");
  const laterYears = years.filter((y) => y.start_date > fromYear.start_date);
  const nextClasses = classes.filter(
    (c) => c.numeric_level === fromClass.numeric_level + 1,
  );
  const [toYearId, setToYearId] = useState(
    laterYears.length > 0 ? String(laterYears[laterYears.length - 1].id) : "",
  );
  const [toClassId, setToClassId] = useState("");
  const [toSections, setToSections] = useState<Section[]>([]);
  const [toSectionId, setToSectionId] = useState("");
  const [selected, setSelected] = useState<Set<number>>(
    () => new Set(active.map((e) => e.student!.id)),
  );
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function chooseClass(value: string) {
    setToClassId(value);
    setToSectionId("");
    setToSections([]);
    if (!value) return;
    sectionService
      .listSections(Number(value), 1, 100)
      .then((res) => setToSections(res.data))
      .catch((err) => setError(errorMessage(err)));
  }

  function toggle(id: number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function handlePromote() {
    const toYear = years.find((y) => y.id === Number(toYearId));
    const toClass = classes.find((c) => c.id === Number(toClassId));
    const toSection = toSections.find((s) => s.id === Number(toSectionId));
    if (!toYear || !toClass || !toSection) return;
    if (
      !window.confirm(
        `Promote ${selected.size} student(s) to ${toClass.name} ${toSection.name} for ${toYear.name}?`,
      )
    )
      return;
    setBusy(true);
    setError(null);
    try {
      const res = await enrollmentService.promote({
        from_year_id: fromYear.id,
        from_section_id: fromSectionId,
        to_year_id: toYear.id,
        to_section_id: toSection.id,
        student_ids: [...selected],
      });
      onDone(
        `${res.promoted} student(s) promoted to ${toClass.name} ${toSection.name} for ${toYear.name}.`,
      );
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section aria-label="Promote students" className="card mb-6 p-5">
      <h2 className="mb-1 text-sm font-semibold">Promote to the next class</h2>
      <p className="mb-3 text-xs text-slate-500">
        Selected students get a new enrollment one class up; this year&apos;s is
        marked promoted. Unticked students (e.g. held back) stay as they are.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-3">
          {error}
        </p>
      )}

      {laterYears.length === 0 || nextClasses.length === 0 ? (
        <p className="text-sm text-slate-500">
          {laterYears.length === 0
            ? "Add the next academic year first (Academic years)."
            : `There's no class at level ${fromClass.numeric_level + 1} to promote into.`}
        </p>
      ) : (
        <>
          <div className="mb-3 flex flex-wrap gap-2">
            <select
              aria-label="Promote into year"
              value={toYearId}
              onChange={(e) => setToYearId(e.target.value)}
              className={inputClass}
            >
              {laterYears.map((y) => (
                <option key={y.id} value={y.id}>
                  {y.name}
                </option>
              ))}
            </select>
            <select
              aria-label="Promote into class"
              value={toClassId}
              onChange={(e) => chooseClass(e.target.value)}
              className={inputClass}
            >
              <option value="">Class…</option>
              {nextClasses.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            <select
              aria-label="Promote into section"
              value={toSectionId}
              disabled={!toClassId}
              onChange={(e) => setToSectionId(e.target.value)}
              className={inputClass}
            >
              <option value="">Section…</option>
              {toSections.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({s.capacity} seats)
                </option>
              ))}
            </select>
          </div>

          {active.length === 0 ? (
            <p className="text-sm text-slate-500">
              No active students in this section.
            </p>
          ) : (
            <ul className="mb-3 grid gap-1 text-sm sm:grid-cols-2">
              {active.map((e) => (
                <li key={e.id}>
                  <label className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={selected.has(e.student!.id)}
                      onChange={() => toggle(e.student!.id)}
                    />
                    <span className="w-8 text-slate-500 tabular-nums">
                      {e.roll}
                    </span>
                    {e.student!.name}
                  </label>
                </li>
              ))}
            </ul>
          )}

          <div className="flex gap-3">
            <button
              type="button"
              disabled={busy || !toSectionId || selected.size === 0}
              onClick={() => void handlePromote()}
              className="btn btn-primary"
            >
              Promote {selected.size} student(s)
            </button>
            <button
              type="button"
              onClick={onCancel}
              className="text-sm text-slate-500 hover:underline"
            >
              Cancel
            </button>
          </div>
        </>
      )}
    </section>
  );
}

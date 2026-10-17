"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatDay } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as academicYearService from "@/services/academicYearService";
import * as classService from "@/services/classService";
import * as enrollmentService from "@/services/enrollmentService";
import * as sectionService from "@/services/sectionService";
import type { AcademicYear } from "@/types/academicYear";
import { ENROLLMENT_STATUSES, type EnrollmentStatus } from "@/types/enrollment";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";
import type { Student } from "@/types/student";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

interface Props {
  student: Student;
  /** enrollment.create (needs the year/class/section pickers too); not archived. */
  canEnroll: boolean;
  /** enrollment.edit; not archived. */
  canEdit: boolean;
  /** Reload the student after a change. */
  onChanged: (message: string) => void;
}

/** This year's class/section/roll, enrollment history, enroll and transfer-out. */
export function StudentEnrollment({
  student,
  canEnroll,
  canEdit,
  onChanged,
}: Props) {
  const { can } = useAuth();
  const current = student.current_enrollment ?? null;
  const history = student.enrollments ?? [];
  // undefined until the years have loaded; null = no current year is set.
  const [currentYear, setCurrentYear] = useState<
    AcademicYear | null | undefined
  >(undefined);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [classId, setClassId] = useState("");
  const [sections, setSections] = useState<Section[]>([]);
  const [sectionId, setSectionId] = useState("");
  const [ending, setEnding] = useState<EnrollmentStatus | null>(null);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const showEnroll = canEnroll && current === null;

  // Pickers for enrolling: state is set only in promise callbacks.
  useEffect(() => {
    if (!showEnroll) return;
    let active = true;
    Promise.all([
      academicYearService.listAcademicYears(1, 100),
      classService.listClasses(1, 100),
    ])
      .then(([years, cls]) => {
        if (!active) return;
        setCurrentYear(years.data.find((y) => y.is_current) ?? null);
        setClasses(cls.data);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [showEnroll]);

  function chooseClass(value: string) {
    setClassId(value);
    setSectionId("");
    setSections([]);
    if (!value) return;
    sectionService
      .listSections(Number(value), 1, 100)
      .then((res) => setSections(res.data))
      .catch((err) => setError(errorMessage(err)));
  }

  async function run(task: () => Promise<unknown>, message: string) {
    setBusy(true);
    setError(null);
    try {
      await task();
      onChanged(message);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
      <h2 className="mb-3 text-sm font-semibold">Enrollment</h2>

      {error && (
        <p role="alert" className="mb-3 text-sm text-red-600">
          {error}
        </p>
      )}

      {current ? (
        <div className="mb-3 text-sm">
          <p>
            <span className="font-medium">
              {current.class?.name} {current.section?.name}
            </span>{" "}
            · Roll {current.roll} · {current.academic_year?.name} ·{" "}
            {labelOf(ENROLLMENT_STATUSES, current.status)}
            {current.ended_on && ` on ${formatDay(current.ended_on)}`}
          </p>
          {current.exit_reason && (
            <p className="text-xs text-gray-500">{current.exit_reason}</p>
          )}
          {current.status === "transferred" && can("enrollment.view") && (
            <Link
              href={`/admin/enrollments/${current.id}/transfer-certificate`}
              className="mt-1 inline-block text-xs hover:underline"
            >
              Transfer certificate →
            </Link>
          )}

          {canEdit && current.status === "active" && (
            <div className="mt-2 flex flex-wrap items-center gap-2">
              {ending === null ? (
                <>
                  <button
                    type="button"
                    onClick={() => setEnding("transferred")}
                    className="text-xs hover:underline"
                  >
                    Transfer out
                  </button>
                  <button
                    type="button"
                    onClick={() => setEnding("left")}
                    className="text-xs hover:underline"
                  >
                    Mark as left
                  </button>
                </>
              ) : (
                <>
                  <input
                    aria-label="Reason"
                    maxLength={255}
                    placeholder={
                      ending === "transferred"
                        ? "Destination school"
                        : "Reason (optional)"
                    }
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    className={`min-w-48 flex-1 ${inputClass}`}
                  />
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() =>
                      void run(
                        () =>
                          enrollmentService.updateEnrollment(current.id, {
                            section_id: current.section!.id,
                            roll: current.roll,
                            status: ending,
                            exit_reason: reason.trim() || null,
                          }),
                        ending === "transferred"
                          ? `${student.name} transferred out.`
                          : `${student.name} marked as left.`,
                      )
                    }
                    className="rounded-md border border-red-300 px-3 py-1 text-sm text-red-600 dark:border-red-900"
                  >
                    {ending === "transferred" ? "Confirm transfer" : "Confirm"}
                  </button>
                  <button
                    type="button"
                    onClick={() => setEnding(null)}
                    className="text-sm text-gray-500 hover:underline"
                  >
                    Cancel
                  </button>
                </>
              )}
            </div>
          )}
        </div>
      ) : (
        <p className="mb-3 text-sm text-gray-500">
          Not enrolled for the current year.
        </p>
      )}

      {showEnroll &&
        currentYear !== undefined &&
        (currentYear === null ? (
          <p className="text-xs text-gray-500">
            Set a current academic year to enroll students.
          </p>
        ) : (
          <div className="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
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
              onChange={(e) => setSectionId(e.target.value)}
              className={inputClass}
            >
              <option value="">Section…</option>
              {sections.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
            <button
              type="button"
              disabled={busy || !sectionId}
              onClick={() =>
                void run(
                  () =>
                    enrollmentService.enroll(
                      student.id,
                      currentYear.id,
                      Number(sectionId),
                      null,
                    ),
                  `${student.name} enrolled for ${currentYear.name}.`,
                )
              }
              className="rounded-md bg-gray-900 px-3 py-1 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
            >
              Enroll for {currentYear.name}
            </button>
          </div>
        ))}

      {history.length > 0 && (
        <details className="mt-3 text-sm">
          <summary className="cursor-pointer text-gray-500">
            History ({history.length})
          </summary>
          <ul className="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
            {history.map((e) => (
              <li key={e.id} className="flex flex-wrap justify-between py-1.5">
                <span>
                  {e.academic_year?.name} · {e.class?.name} {e.section?.name} ·
                  Roll {e.roll}
                </span>
                <span className="text-gray-500">
                  {labelOf(ENROLLMENT_STATUSES, e.status)}
                  {e.ended_on && ` ${formatDay(e.ended_on)}`}
                  {e.status === "transferred" && can("enrollment.view") && (
                    <Link
                      href={`/admin/enrollments/${e.id}/transfer-certificate`}
                      className="ml-2 text-gray-900 hover:underline dark:text-gray-100"
                    >
                      TC
                    </Link>
                  )}
                </span>
              </li>
            ))}
          </ul>
        </details>
      )}
    </section>
  );
}

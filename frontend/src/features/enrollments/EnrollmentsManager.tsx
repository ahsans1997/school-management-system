"use client";

import Link from "next/link";
import { useCallback, useEffect, useState, type FormEvent } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as academicYearService from "@/services/academicYearService";
import * as classService from "@/services/classService";
import * as enrollmentService from "@/services/enrollmentService";
import * as sectionService from "@/services/sectionService";
import * as studentService from "@/services/studentService";
import type { AcademicYear } from "@/types/academicYear";
import {
  ENROLLMENT_STATUSES,
  type Enrollment,
  type EnrollmentPage,
  type EnrollmentStatus,
} from "@/types/enrollment";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";
import type { Student } from "@/types/student";
import { PromotePanel } from "./PromotePanel";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

interface RowDraft {
  id: number;
  roll: string;
  status: EnrollmentStatus;
  exit_reason: string;
}

/** Enrollments for a year, by class and section: enroll, edit roll/status, promote. */
export function EnrollmentsManager() {
  const { can } = useAuth();
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [sections, setSections] = useState<Section[]>([]);
  const [yearId, setYearId] = useState("");
  const [classId, setClassId] = useState("");
  const [sectionId, setSectionId] = useState("");
  const [page, setPage] = useState<EnrollmentPage | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [editing, setEditing] = useState<RowDraft | null>(null);
  const [promoting, setPromoting] = useState(false);
  // Enroll form
  const [search, setSearch] = useState("");
  const [found, setFound] = useState<Student[] | null>(null);
  const [picked, setPicked] = useState<Student | null>(null);
  const [roll, setRoll] = useState("");

  const canCreate = can("enrollment.create");
  const canEdit = can("enrollment.edit");

  const load = useCallback(
    async (year: string, cls: string, section: string) => {
      try {
        setPage(
          await enrollmentService.listEnrollments({
            academicYearId: year ? Number(year) : null,
            classId: cls ? Number(cls) : null,
            sectionId: section ? Number(section) : null,
          }),
        );
      } catch (err) {
        setError(errorMessage(err));
      }
    },
    [],
  );

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    Promise.all([
      academicYearService.listAcademicYears(1, 100),
      classService.listClasses(1, 100),
      enrollmentService.listEnrollments(),
    ])
      .then(([y, c, e]) => {
        if (!active) return;
        setYears(y.data);
        setClasses(c.data);
        setPage(e);
        setYearId(e.academic_year_id ? String(e.academic_year_id) : "");
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);

  function changeYear(value: string) {
    setYearId(value);
    setPromoting(false);
    void load(value, classId, sectionId);
  }

  function changeClass(value: string) {
    setClassId(value);
    setSectionId("");
    setSections([]);
    setPromoting(false);
    void load(yearId, value, "");
    if (value) {
      sectionService
        .listSections(Number(value), 1, 100)
        .then((res) => setSections(res.data))
        .catch((err) => setError(errorMessage(err)));
    }
  }

  function changeSection(value: string) {
    setSectionId(value);
    setPromoting(false);
    void load(yearId, classId, value);
  }

  async function run(task: () => Promise<unknown>, success: string) {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await task();
      setNotice(success);
      await load(yearId, classId, sectionId);
      return true;
    } catch (err) {
      setError(errorMessage(err));
      return false;
    } finally {
      setBusy(false);
    }
  }

  async function findStudents(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPicked(null);
    try {
      const res = await studentService.listStudents({
        search: search.trim(),
        perPage: 10,
      });
      setFound(res.data);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function handleEnroll(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!picked) return;
    const section = sections.find((s) => s.id === Number(sectionId));
    if (
      await run(
        () =>
          enrollmentService.enroll(
            picked.id,
            Number(yearId),
            Number(sectionId),
            roll ? Number(roll) : null,
          ),
        `${picked.name} enrolled in section ${section?.name ?? ""}.`,
      )
    ) {
      setPicked(null);
      setFound(null);
      setSearch("");
      setRoll("");
    }
  }

  async function saveRow(e: Enrollment) {
    if (!editing) return;
    if (
      await run(
        () =>
          enrollmentService.updateEnrollment(e.id, {
            section_id: e.section!.id,
            roll: editing.roll ? Number(editing.roll) : null,
            status: editing.status,
            exit_reason:
              editing.status === "active" ? null : editing.exit_reason || null,
          }),
        `${e.student?.name ?? "Enrollment"} updated.`,
      )
    ) {
      setEditing(null);
    }
  }

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  const year = years.find((y) => y.id === Number(yearId)) ?? null;
  const cls = classes.find((c) => c.id === Number(classId)) ?? null;
  const enrollments = page?.data ?? [];
  const seats = page?.seats;

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Enrollments</h1>
      <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
        Each student&apos;s section and roll for a year. Pick a class and
        section to enroll students or promote them at year end.
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="mb-4 text-sm text-green-600">{notice}</p>}

      {years.length === 0 ? (
        <p className="text-sm">
          Add an academic year first under{" "}
          <Link href="/admin/academic-years" className="underline">
            Academic years
          </Link>
          .
        </p>
      ) : (
        <>
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <select
              aria-label="Academic year"
              value={yearId}
              onChange={(e) => changeYear(e.target.value)}
              className={inputClass}
            >
              {years.map((y) => (
                <option key={y.id} value={y.id}>
                  {y.name}
                  {y.is_current ? " (current)" : ""}
                </option>
              ))}
            </select>
            <select
              aria-label="Class"
              value={classId}
              onChange={(e) => changeClass(e.target.value)}
              className={inputClass}
            >
              <option value="">All classes</option>
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
              onChange={(e) => changeSection(e.target.value)}
              className={inputClass}
            >
              <option value="">All sections</option>
              {sections.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
            {seats && (
              <span
                className={`ml-auto text-sm tabular-nums ${seats.taken >= seats.capacity ? "text-red-600" : "text-gray-500"}`}
              >
                {seats.taken} / {seats.capacity} seats taken
              </span>
            )}
          </div>

          {sectionId && canCreate && year && (
            <div className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">Enroll a student</h2>
                {cls && !promoting && (
                  <button
                    type="button"
                    onClick={() => setPromoting(true)}
                    className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
                  >
                    Promote this section…
                  </button>
                )}
              </div>
              <form
                role="search"
                onSubmit={findStudents}
                className="flex flex-wrap gap-2"
              >
                <input
                  type="search"
                  aria-label="Find a student"
                  placeholder="Find a student by name or ID"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className={`min-w-48 flex-1 ${inputClass}`}
                />
                <button
                  type="submit"
                  className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
                >
                  Find
                </button>
              </form>
              {found !== null && (
                <ul className="mt-2 text-sm">
                  {found.length === 0 && (
                    <li className="py-1 text-gray-500">No match.</li>
                  )}
                  {found.map((s) => (
                    <li key={s.id} className="flex items-center gap-2 py-1">
                      <span className="flex-1">
                        {s.name}{" "}
                        <span className="text-xs text-gray-500 tabular-nums">
                          {s.student_id}
                          {s.current_enrollment &&
                            ` · ${s.current_enrollment.class?.name} ${s.current_enrollment.section?.name} this year`}
                        </span>
                      </span>
                      <button
                        type="button"
                        onClick={() => setPicked(s)}
                        className="text-xs hover:underline"
                      >
                        Select
                      </button>
                    </li>
                  ))}
                </ul>
              )}
              {picked && (
                <form
                  onSubmit={handleEnroll}
                  aria-label="Enroll student"
                  className="mt-3 flex flex-wrap items-center gap-2 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-900"
                >
                  <span className="font-medium">{picked.name}</span>
                  <input
                    type="number"
                    min={1}
                    max={9999}
                    aria-label="Roll"
                    placeholder="Roll (auto)"
                    value={roll}
                    onChange={(e) => setRoll(e.target.value)}
                    className={`w-28 ${inputClass}`}
                  />
                  <button
                    type="submit"
                    disabled={busy}
                    className="rounded-md bg-gray-900 px-3 py-1 text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
                  >
                    Enroll for {year.name}
                  </button>
                  <button
                    type="button"
                    onClick={() => setPicked(null)}
                    className="text-gray-500 hover:underline"
                  >
                    Cancel
                  </button>
                </form>
              )}
            </div>
          )}

          {promoting && year && cls && (
            <PromotePanel
              fromYear={year}
              fromClass={cls}
              fromSectionId={Number(sectionId)}
              enrollments={enrollments}
              years={years}
              classes={classes}
              onCancel={() => setPromoting(false)}
              onDone={(message) => {
                setPromoting(false);
                setNotice(message);
                void load(yearId, classId, sectionId);
              }}
            />
          )}

          {enrollments.length === 0 ? (
            <p className="text-sm text-gray-500">
              No enrollments for this selection yet.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <th className="py-2 pr-3 font-medium">Class</th>
                    <th className="py-2 pr-3 font-medium">Roll</th>
                    <th className="py-2 pr-3 font-medium">Student</th>
                    <th className="py-2 pr-3 font-medium">Status</th>
                    {canEdit && (
                      <th className="py-2 text-right font-medium">Actions</th>
                    )}
                  </tr>
                </thead>
                <tbody>
                  {enrollments.map((e) =>
                    editing?.id === e.id ? (
                      <tr
                        key={e.id}
                        className="border-b border-gray-100 dark:border-gray-900"
                      >
                        <td className="py-2 pr-3 whitespace-nowrap">
                          {e.class?.name} {e.section?.name}
                        </td>
                        <td className="py-2 pr-2">
                          <input
                            type="number"
                            aria-label="Roll"
                            min={1}
                            max={9999}
                            value={editing.roll}
                            onChange={(ev) =>
                              setEditing({ ...editing, roll: ev.target.value })
                            }
                            className={`w-20 ${inputClass}`}
                          />
                        </td>
                        <td className="py-2 pr-3">{e.student?.name}</td>
                        <td className="space-y-1 py-2 pr-2">
                          <select
                            aria-label="Status"
                            value={editing.status}
                            onChange={(ev) =>
                              setEditing({
                                ...editing,
                                status: ev.target.value as EnrollmentStatus,
                              })
                            }
                            className={inputClass}
                          >
                            {ENROLLMENT_STATUSES.map((s) => (
                              <option key={s.value} value={s.value}>
                                {s.label}
                              </option>
                            ))}
                          </select>
                          {editing.status !== "active" && (
                            <input
                              aria-label="Reason"
                              maxLength={255}
                              placeholder="Reason, e.g. moved to Dhaka"
                              value={editing.exit_reason}
                              onChange={(ev) =>
                                setEditing({
                                  ...editing,
                                  exit_reason: ev.target.value,
                                })
                              }
                              className={`block w-full ${inputClass}`}
                            />
                          )}
                        </td>
                        <td className="space-x-3 py-2 text-right whitespace-nowrap">
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => void saveRow(e)}
                            className="text-xs font-medium hover:underline"
                          >
                            Save
                          </button>
                          <button
                            type="button"
                            onClick={() => setEditing(null)}
                            className="text-xs text-gray-500 hover:underline"
                          >
                            Cancel
                          </button>
                        </td>
                      </tr>
                    ) : (
                      <tr
                        key={e.id}
                        className="border-b border-gray-100 dark:border-gray-900"
                      >
                        <td className="py-2 pr-3 whitespace-nowrap">
                          {e.class?.name} {e.section?.name}
                        </td>
                        <td className="py-2 pr-3 tabular-nums">{e.roll}</td>
                        <td className="py-2 pr-3">
                          {e.student && (
                            <Link
                              href={`/admin/students/${e.student.id}`}
                              className="font-medium hover:underline"
                            >
                              {e.student.name}
                            </Link>
                          )}
                          {e.student?.archived && (
                            <span className="ml-1 text-xs text-gray-500">
                              (archived)
                            </span>
                          )}
                        </td>
                        <td className="py-2 pr-3">
                          {labelOf(ENROLLMENT_STATUSES, e.status)}
                          {e.exit_reason && (
                            <span className="block text-xs text-gray-500">
                              {e.exit_reason}
                            </span>
                          )}
                        </td>
                        {canEdit && (
                          <td className="py-2 text-right">
                            <button
                              type="button"
                              onClick={() =>
                                setEditing({
                                  id: e.id,
                                  roll: String(e.roll),
                                  status: e.status,
                                  exit_reason: e.exit_reason ?? "",
                                })
                              }
                              className="text-xs text-gray-600 hover:underline dark:text-gray-300"
                            >
                              Edit
                            </button>
                          </td>
                        )}
                      </tr>
                    ),
                  )}
                </tbody>
              </table>
            </div>
          )}
          {page && page.meta.total > enrollments.length && (
            <p className="mt-3 text-xs text-gray-500">
              Showing the first {enrollments.length} of {page.meta.total}.
              Narrow by class or section to see the rest.
            </p>
          )}
        </>
      )}
    </div>
  );
}

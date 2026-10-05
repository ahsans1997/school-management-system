"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { DetailsList } from "@/components/DetailsList";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as academicYearService from "@/services/academicYearService";
import * as classService from "@/services/classService";
import * as examService from "@/services/examService";
import * as sectionService from "@/services/sectionService";
import type { AcademicYear } from "@/types/academicYear";
import {
  EXAM_TYPES,
  type ExamDetail as ExamDetailData,
  type ExamInput,
  type ExamPaper,
  type GradeScale,
} from "@/types/exam";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";
import { ExamForm } from "./ExamForm";
import { ExamScheduleTable } from "./ExamScheduleTable";
import { examDates } from "./ExamsList";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

/** One exam: header, the classes taking it, and its timetable. */
export function ExamDetail({ examId }: { examId: number }) {
  const { can } = useAuth();
  const [detail, setDetail] = useState<ExamDetailData | null>(null);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [scales, setScales] = useState<GradeScale[]>([]);
  const [editing, setEditing] = useState(false);
  const [addClassId, setAddClassId] = useState("");
  const [filterClassId, setFilterClassId] = useState("");
  const [sections, setSections] = useState<Section[]>([]);
  const [filterSectionId, setFilterSectionId] = useState("");
  const [papers, setPapers] = useState<ExamPaper[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    Promise.all([
      examService.getExam(examId),
      classService.listClasses(1, 100),
      academicYearService.listAcademicYears(1, 100),
      examService.listGradeScales(),
    ])
      .then(([d, classPage, yearPage, scaleList]) => {
        if (!active) return;
        setDetail(d);
        setClasses(classPage.data);
        setYears(yearPage.data);
        setScales(scaleList);
        if (d.data.archived) return;
        return examService.getSchedule(examId).then((s) => {
          if (active) setPapers(s.papers);
        });
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [examId]);

  function showSchedule(classId: string, sectionId: string) {
    setFilterClassId(classId);
    setFilterSectionId(sectionId);
    examService
      .getSchedule(examId, {
        classId: classId ? Number(classId) : undefined,
        sectionId: sectionId ? Number(sectionId) : undefined,
      })
      .then((s) => setPapers(s.papers))
      .catch((err) => setError(errorMessage(err)));
  }

  function chooseFilterClass(value: string) {
    setSections([]);
    showSchedule(value, "");
    if (!value) return;
    sectionService
      .listSections(Number(value), 1, 100)
      .then((res) => setSections(res.data))
      .catch((err) => setError(errorMessage(err)));
  }

  async function save(input: ExamInput) {
    const exam = await examService.updateExam(examId, input);
    setDetail((d) => (d ? { ...d, data: exam } : d));
    setEditing(false);
    setNotice("Exam updated.");
  }

  async function archive() {
    if (!detail) return;
    if (
      !window.confirm(
        `Archive ${detail.data.name}? It disappears from the lists; its papers are kept and it can be restored.`,
      )
    )
      return;
    setError(null);
    try {
      await examService.archiveExam(examId);
      setDetail({ ...detail, data: { ...detail.data, archived: true } });
      setPapers([]);
      setNotice("Exam archived.");
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function restore() {
    if (!detail) return;
    setError(null);
    try {
      const exam = await examService.restoreExam(examId);
      setDetail({ ...detail, data: exam });
      setNotice("Exam restored.");
      showSchedule("", "");
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  if (!detail) {
    return (
      <div className="p-8 text-sm">
        {error ? (
          <p role="alert" className="text-red-600">
            {error}
          </p>
        ) : (
          <p className="text-gray-500">Loading…</p>
        )}
      </div>
    );
  }

  const exam = detail.data;
  const canEdit = can("exam.edit") && !exam.archived;
  const taking = new Set(detail.classes.map((c) => c.id));
  const notTaking = classes.filter((c) => !taking.has(c.id));

  return (
    <div className="mx-auto max-w-5xl space-y-6 p-6">
      <div>
        <Link
          href="/admin/exams"
          className="text-sm text-gray-500 hover:underline"
        >
          ← Exams
        </Link>
        <div className="mt-1 flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-2xl font-semibold">
            {exam.name}
            {exam.archived && (
              <span className="ml-2 rounded bg-gray-200 px-2 py-0.5 align-middle text-xs font-normal text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                Archived
              </span>
            )}
          </h1>
          <div className="flex gap-2">
            {canEdit && !editing && (
              <button
                type="button"
                onClick={() => {
                  setEditing(true);
                  setNotice(null);
                }}
                className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
              >
                Edit
              </button>
            )}
            {can("exam.delete") &&
              (exam.archived ? (
                <button
                  type="button"
                  onClick={() => void restore()}
                  className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
                >
                  Restore
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => void archive()}
                  className="rounded-md border border-red-300 px-3 py-1 text-sm text-red-700 dark:border-red-800 dark:text-red-400"
                >
                  Archive
                </button>
              ))}
          </div>
        </div>
      </div>

      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="text-sm text-green-600">{notice}</p>}

      <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
        {editing ? (
          <ExamForm
            exam={exam}
            years={years}
            scales={scales}
            submitLabel="Save changes"
            onSubmit={save}
            onCancel={() => setEditing(false)}
          />
        ) : (
          <DetailsList
            rows={[
              ["Type", labelOf(EXAM_TYPES, exam.type)],
              ["Academic year", exam.academic_year?.name],
              ["Dates", examDates(exam)],
              ["Grade scale", exam.grade_scale?.name],
            ]}
          />
        )}
      </section>

      <section>
        <h2 className="mb-2 text-sm font-semibold">Classes</h2>
        {detail.classes.length === 0 ? (
          <p className="mb-3 text-sm text-gray-500">
            No class takes this exam yet{canEdit ? ": add one below." : "."}
          </p>
        ) : (
          <ul className="mb-3 divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm dark:divide-gray-800 dark:border-gray-800">
            {detail.classes.map((c) => (
              <li
                key={c.id}
                className="flex items-center justify-between gap-3 px-3 py-2"
              >
                <span>
                  <span className="font-medium">{c.name}</span>
                  <span className="ml-2 text-gray-500">
                    {c.papers === 1 ? "1 paper" : `${c.papers} papers`}
                  </span>
                </span>
                {!exam.archived && (
                  <Link
                    href={`/admin/exams/${exam.id}/classes/${c.id}`}
                    className="text-sm hover:underline"
                  >
                    {canEdit ? "Edit papers" : "View papers"}
                  </Link>
                )}
              </li>
            ))}
          </ul>
        )}
        {canEdit && notTaking.length > 0 && (
          <div className="flex flex-wrap gap-2">
            <select
              aria-label="Class to add"
              value={addClassId}
              onChange={(e) => setAddClassId(e.target.value)}
              className={inputClass}
            >
              <option value="">Add a class…</option>
              {notTaking.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            {addClassId && (
              <Link
                href={`/admin/exams/${exam.id}/classes/${addClassId}`}
                className="rounded-md bg-gray-900 px-3 py-1 text-sm text-white dark:bg-gray-100 dark:text-gray-900"
              >
                Set its papers
              </Link>
            )}
          </div>
        )}
      </section>

      {!exam.archived && (
        <section>
          <div className="mb-2 flex flex-wrap items-center gap-2">
            <h2 className="mr-auto text-sm font-semibold">Schedule</h2>
            <select
              aria-label="Schedule for class"
              value={filterClassId}
              onChange={(e) => chooseFilterClass(e.target.value)}
              className={inputClass}
            >
              <option value="">All classes</option>
              {detail.classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            <select
              aria-label="Schedule for section"
              value={filterSectionId}
              disabled={!filterClassId}
              onChange={(e) => showSchedule(filterClassId, e.target.value)}
              className={inputClass}
            >
              <option value="">All sections</option>
              {sections.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
            <button
              type="button"
              onClick={() => window.print()}
              className="rounded-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-700"
            >
              Print
            </button>
          </div>
          <ExamScheduleTable papers={papers} showClass={!filterClassId} />
        </section>
      )}
    </div>
  );
}

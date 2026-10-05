"use client";

import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import * as classService from "@/services/classService";
import * as sectionService from "@/services/sectionService";
import * as subjectService from "@/services/subjectService";
import * as teacherService from "@/services/teacherService";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";
import type { ClassSubject } from "@/types/subject";
import type { Teacher } from "@/types/teacher";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";
const button =
  "rounded-md border border-gray-300 px-3 py-1 text-sm disabled:opacity-50 dark:border-gray-700";

interface Props {
  teacher: Teacher;
  /** teacher.edit, not archived, and able to read classes/sections/subjects for the pickers. */
  canAssign: boolean;
  onChange: (teacher: Teacher) => void;
}

/** What the teacher teaches (subject per section) and which sections they're class teacher of. */
export function TeacherTeaching({ teacher, canAssign, onChange }: Props) {
  const assignments = teacher.assignments ?? [];
  const classTeacherOf = teacher.class_teacher_of ?? [];
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [classId, setClassId] = useState("");
  const [sections, setSections] = useState<Section[]>([]);
  const [subjects, setSubjects] = useState<ClassSubject[]>([]);
  const [sectionId, setSectionId] = useState("");
  const [subjectId, setSubjectId] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Class picker options: state is set only in promise callbacks.
  useEffect(() => {
    if (!canAssign) return;
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
  }, [canAssign]);

  function chooseClass(value: string) {
    setClassId(value);
    setSectionId("");
    setSubjectId("");
    setSections([]);
    setSubjects([]);
    if (!value) return;
    const id = Number(value);
    Promise.all([
      sectionService.listSections(id, 1, 100),
      subjectService.listClassSubjects(id, 1, 100),
    ])
      .then(([sectionPage, subjectPage]) => {
        setSections(sectionPage.data);
        setSubjects(subjectPage.data);
      })
      .catch((err) => setError(errorMessage(err)));
  }

  async function run(task: () => Promise<Teacher>) {
    setBusy(true);
    setError(null);
    try {
      onChange(await task());
      return true;
    } catch (err) {
      setError(errorMessage(err));
      return false;
    } finally {
      setBusy(false);
    }
  }

  async function assignSubject() {
    if (
      await run(() =>
        teacherService.assignSubject(
          teacher.id,
          Number(sectionId),
          Number(subjectId),
        ),
      )
    ) {
      setSubjectId("");
    }
  }

  return (
    <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
      <h2 className="mb-3 text-sm font-semibold">Teaching</h2>

      {error && (
        <p role="alert" className="mb-3 text-sm text-red-600">
          {error}
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <h3 className="mb-1 text-xs font-medium text-gray-500 uppercase">
            Subjects
          </h3>
          {assignments.length === 0 ? (
            <p className="text-sm text-gray-500">No subjects assigned yet.</p>
          ) : (
            <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
              {assignments.map((a) => (
                <li key={a.id} className="flex items-center gap-2 py-1.5">
                  <span className="flex-1">
                    {a.class.name} {a.section.name} —{" "}
                    <span className="font-medium">{a.subject.name}</span>
                  </span>
                  {canAssign && (
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() =>
                        void run(() =>
                          teacherService.unassignSubject(teacher.id, a.id),
                        )
                      }
                      className="text-xs text-red-600 hover:underline"
                    >
                      Remove
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>

        <div>
          <h3 className="mb-1 text-xs font-medium text-gray-500 uppercase">
            Class teacher of
          </h3>
          {classTeacherOf.length === 0 ? (
            <p className="text-sm text-gray-500">No section.</p>
          ) : (
            <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
              {classTeacherOf.map((c) => (
                <li
                  key={c.section.id}
                  className="flex items-center gap-2 py-1.5"
                >
                  <span className="flex-1 font-medium">
                    {c.class.name} {c.section.name}
                  </span>
                  {canAssign && (
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() =>
                        void run(() =>
                          teacherService.removeClassTeacher(
                            teacher.id,
                            c.section.id,
                          ),
                        )
                      }
                      className="text-xs text-red-600 hover:underline"
                    >
                      Remove
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      {canAssign && (
        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
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
          <select
            aria-label="Subject"
            value={subjectId}
            disabled={!classId}
            onChange={(e) => setSubjectId(e.target.value)}
            className={inputClass}
          >
            <option value="">Subject…</option>
            {subjects.map((cs) => (
              <option key={cs.subject.id} value={cs.subject.id}>
                {cs.subject.name}
              </option>
            ))}
          </select>
          <button
            type="button"
            disabled={busy || !sectionId || !subjectId}
            onClick={() => void assignSubject()}
            className={button}
          >
            Assign subject
          </button>
          <button
            type="button"
            disabled={busy || !sectionId}
            onClick={() =>
              void run(() =>
                teacherService.setClassTeacher(teacher.id, Number(sectionId)),
              )
            }
            className={button}
          >
            Make class teacher
          </button>
          {classId && sections.length === 0 && (
            <p className="w-full text-xs text-gray-500">
              This class has no sections yet.
            </p>
          )}
          {classId && subjects.length === 0 && (
            <p className="w-full text-xs text-gray-500">
              No subjects are assigned to this class yet.
            </p>
          )}
        </div>
      )}
    </section>
  );
}

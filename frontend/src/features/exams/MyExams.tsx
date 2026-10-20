"use client";

import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as examService from "@/services/examService";
import { EXAM_TYPES, type MyExams as MyExamsData } from "@/types/exam";
import { ExamScheduleTable } from "./ExamScheduleTable";
import { examDates } from "./ExamsList";
import { InlineLoader } from "@/components/Loading";

/**
 * The caller's upcoming exam timetables: a teacher's sections, a
 * student's own section, or each of a parent's children.
 */
export function MyExams() {
  const [data, setData] = useState<MyExamsData | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    examService
      .getMyExams()
      .then((d) => {
        if (active) setData(d);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  const groups = data?.groups.filter((g) => g.section) ?? [];
  const anyExams = groups.some((g) => g.exams.length > 0);

  return (
    <div className="mx-auto max-w-5xl space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-semibold">
          Exams
          {data && (
            <span className="ml-2 text-base font-normal text-slate-500">
              {data.academic_year.name}
            </span>
          )}
        </h1>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          Upcoming and current exams.
        </p>
      </div>

      {error ? (
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      ) : !data ? (
        <InlineLoader />
      ) : data.groups.length === 0 ? (
        <p className="text-sm text-slate-500">
          {data.type === "teacher"
            ? "You don't teach any sections yet."
            : "Your login isn't linked to a student yet."}
        </p>
      ) : !anyExams ? (
        <p className="text-sm text-slate-500">No upcoming exams.</p>
      ) : (
        data.groups.map((g) => (
          <section key={`${g.student?.id ?? "t"}-${g.section?.id ?? "none"}`}>
            <h2 className="mb-2 text-sm font-semibold">
              {g.student && data.type === "parent" && `${g.student.name} · `}
              {g.section
                ? `${g.section.class} ${g.section.name}`
                : "Not enrolled this year"}
            </h2>
            {g.section && g.exams.length === 0 && (
              <p className="text-sm text-slate-500">No upcoming exams.</p>
            )}
            <div className="space-y-4">
              {g.exams.map((e) => (
                <div key={e.id} className="card p-5">
                  <h3 className="font-medium">{e.name}</h3>
                  <p className="mb-3 text-xs text-slate-500">
                    {labelOf(EXAM_TYPES, e.type)} · {examDates(e)}
                  </p>
                  <ExamScheduleTable papers={e.papers} />
                </div>
              ))}
            </div>
          </section>
        ))
      )}
    </div>
  );
}

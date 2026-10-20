"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import * as examService from "@/services/examService";
import type { ClassPapers, ExamPaper, ExamPaperInput } from "@/types/exam";
import { ExamScheduleTable } from "./ExamScheduleTable";
import { examDates } from "./ExamsList";
import { PageLoader } from "@/components/Loading";

const inputClass = "input input-sm";

/** A paper as typed; "" means blank (section "" = all sections). */
interface Row {
  key: number;
  class_subject_id: string;
  section_id: string;
  full_marks: string;
  pass_marks: string;
  cq_marks: string;
  mcq_marks: string;
  practical_marks: string;
  exam_date: string;
  start_time: string;
  end_time: string;
}

let nextKey = 1;

const text = (v: number | null) => (v === null ? "" : String(v));
const num = (v: string) => (v.trim() === "" ? null : Number(v));

function toRows(papers: ExamPaper[]): Row[] {
  return papers.map((p) => ({
    key: nextKey++,
    class_subject_id: String(p.class_subject_id),
    section_id: p.section ? String(p.section.id) : "",
    full_marks: text(p.full_marks),
    pass_marks: text(p.pass_marks),
    cq_marks: text(p.cq_marks),
    mcq_marks: text(p.mcq_marks),
    practical_marks: text(p.practical_marks),
    exam_date: p.exam_date ?? "",
    start_time: p.start_time ?? "",
    end_time: p.end_time ?? "",
  }));
}

function blankRow(classSubjectId: string, after?: Row): Row {
  return {
    key: nextKey++,
    class_subject_id: classSubjectId,
    section_id: "",
    full_marks: "100",
    pass_marks: "33",
    cq_marks: "",
    mcq_marks: "",
    practical_marks: "",
    exam_date: "",
    start_time: after?.start_time ?? "",
    end_time: after?.end_time ?? "",
  };
}

function toInput(rows: Row[]): ExamPaperInput[] {
  return rows.map((r) => ({
    class_subject_id: Number(r.class_subject_id),
    section_id: r.section_id ? Number(r.section_id) : null,
    full_marks: Number(r.full_marks),
    pass_marks: Number(r.pass_marks),
    cq_marks: num(r.cq_marks),
    mcq_marks: num(r.mcq_marks),
    practical_marks: num(r.practical_marks),
    exam_date: r.exam_date || null,
    start_time: r.start_time || null,
    end_time: r.end_time || null,
  }));
}

/** The papers one class sits in an exam: marks, the optional split, and when. */
export function ExamPapersEditor({
  examId,
  classId,
}: {
  examId: number;
  classId: number;
}) {
  const { can } = useAuth();
  const canEdit = can("exam.edit");
  const [data, setData] = useState<ClassPapers | null>(null);
  const [rows, setRows] = useState<Row[]>([]);
  const [rowErrors, setRowErrors] = useState<Record<number, string[]>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    examService
      .getClassPapers(examId, classId)
      .then((d) => {
        if (!active) return;
        setData(d);
        // A class not in the exam yet starts with all its subjects (unsaved).
        setRows(
          d.papers.length > 0
            ? toRows(d.papers)
            : d.subjects.map((s) => blankRow(String(s.class_subject_id))),
        );
        if (d.papers.length === 0 && d.subjects.length > 0) {
          setNotice(
            "Not saved yet: these are the class's subjects with 100 / 33 marks. Adjust them, then save.",
          );
        }
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [examId, classId]);

  function update(key: number, patch: Partial<Row>) {
    setRows((rs) => rs.map((r) => (r.key === key ? { ...r, ...patch } : r)));
  }

  function addRow() {
    if (!data) return;
    const used = new Set(rows.map((r) => r.class_subject_id));
    const next =
      data.subjects.find((s) => !used.has(String(s.class_subject_id))) ??
      data.subjects[0];
    if (!next) return;
    setRows((rs) => [
      ...rs,
      blankRow(String(next.class_subject_id), rs[rs.length - 1]),
    ]);
  }

  async function save() {
    setBusy(true);
    setError(null);
    setNotice(null);
    setRowErrors({});
    try {
      const saved = await examService.saveClassPapers(
        examId,
        classId,
        toInput(rows),
      );
      setData(saved);
      setRows(toRows(saved.papers));
      setNotice(
        saved.papers.length === 0
          ? `${saved.class.name} is no longer in this exam.`
          : `Saved ${saved.papers.length} ${saved.papers.length === 1 ? "paper" : "papers"} for ${saved.class.name}.`,
      );
    } catch (err) {
      setError(errorMessage(err));
      // Pin errors to their rows: keys "subjects.N.field".
      if (err instanceof ApiRequestError && err.error.errors) {
        const byRow: Record<number, string[]> = {};
        for (const [key, messages] of Object.entries(err.error.errors)) {
          const match = /^subjects\.(\d+)/.exec(key);
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

  if (!data) {
    return error ? (
      <div className="p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  const marksInput = (
    r: Row,
    field:
      | "full_marks"
      | "pass_marks"
      | "cq_marks"
      | "mcq_marks"
      | "practical_marks",
    label: string,
  ) => (
    <input
      type="number"
      aria-label={label}
      min={0}
      max={1000}
      step="0.01"
      value={r[field]}
      onChange={(e) =>
        update(r.key, { [field]: e.target.value } as Partial<Row>)
      }
      className={`w-16 ${inputClass}`}
    />
  );

  return (
    <div className="mx-auto max-w-6xl p-6">
      <Link
        href={`/admin/exams/${examId}`}
        className="text-sm text-slate-500 hover:underline"
      >
        ← {data.exam.name}
      </Link>
      <h1 className="mt-1 mb-1 text-2xl font-semibold">
        {data.class.name} papers
      </h1>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        {examDates(data.exam)}. A paper is sat by every section unless you pick
        one. Split the full marks into CQ / MCQ / practical only if the paper
        has parts; they must add up.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}
      {notice && <p className="alert alert-success mb-4">{notice}</p>}

      {!canEdit ? (
        <ExamScheduleTable papers={data.papers} />
      ) : data.subjects.length === 0 ? (
        <p className="text-sm text-slate-500">
          {data.class.name} has no subjects yet. Assign them under Classes →
          Subjects first.
        </p>
      ) : (
        <>
          {rows.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-xs text-slate-500">
                    <th className="pr-2 pb-1 font-medium">Subject</th>
                    <th className="pr-2 pb-1 font-medium">Sections</th>
                    <th className="pr-2 pb-1 font-medium">Full</th>
                    <th className="pr-2 pb-1 font-medium">Pass</th>
                    <th className="pr-2 pb-1 font-medium">CQ</th>
                    <th className="pr-2 pb-1 font-medium">MCQ</th>
                    <th className="pr-2 pb-1 font-medium">Practical</th>
                    <th className="pr-2 pb-1 font-medium">Date</th>
                    <th className="pr-2 pb-1 font-medium">Start</th>
                    <th className="pr-2 pb-1 font-medium">End</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r, i) => (
                    <tr key={r.key} className="align-top">
                      <td className="py-1 pr-2">
                        <select
                          aria-label="Subject"
                          value={r.class_subject_id}
                          onChange={(e) =>
                            update(r.key, { class_subject_id: e.target.value })
                          }
                          className={`max-w-44 ${inputClass}`}
                        >
                          {data.subjects.map((s) => (
                            <option
                              key={s.class_subject_id}
                              value={s.class_subject_id}
                            >
                              {s.subject.name}
                            </option>
                          ))}
                        </select>
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
                        <select
                          aria-label="Sections"
                          value={r.section_id}
                          onChange={(e) =>
                            update(r.key, { section_id: e.target.value })
                          }
                          className={inputClass}
                        >
                          <option value="">All</option>
                          {data.sections.map((s) => (
                            <option key={s.id} value={s.id}>
                              {s.name} only
                            </option>
                          ))}
                        </select>
                      </td>
                      <td className="py-1 pr-2">
                        {marksInput(r, "full_marks", "Full marks")}
                      </td>
                      <td className="py-1 pr-2">
                        {marksInput(r, "pass_marks", "Pass marks")}
                      </td>
                      <td className="py-1 pr-2">
                        {marksInput(r, "cq_marks", "CQ marks")}
                      </td>
                      <td className="py-1 pr-2">
                        {marksInput(r, "mcq_marks", "MCQ marks")}
                      </td>
                      <td className="py-1 pr-2">
                        {marksInput(r, "practical_marks", "Practical marks")}
                      </td>
                      <td className="py-1 pr-2">
                        <input
                          type="date"
                          aria-label="Date"
                          min={data.exam.start_date}
                          max={data.exam.end_date ?? data.exam.start_date}
                          value={r.exam_date}
                          onChange={(e) =>
                            update(r.key, { exam_date: e.target.value })
                          }
                          className={inputClass}
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
                      <td className="py-1">
                        <button
                          type="button"
                          aria-label="Remove paper"
                          onClick={() =>
                            setRows((rs) => rs.filter((x) => x.key !== r.key))
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
          {rows.length === 0 && (
            <p className="text-sm text-slate-500">
              No papers: saving takes {data.class.name} out of this exam.
            </p>
          )}
          <div className="mt-3 flex flex-wrap gap-3">
            <button
              type="button"
              onClick={addRow}
              className="btn btn-secondary"
            >
              Add paper
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => void save()}
              className="btn btn-primary"
            >
              {busy ? "Saving…" : "Save papers"}
            </button>
          </div>
        </>
      )}
    </div>
  );
}

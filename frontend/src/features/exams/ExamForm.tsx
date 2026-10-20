"use client";

import { useState, type FormEvent } from "react";
import { FormField, fieldInputClass } from "@/components/FormField";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import type { AcademicYear } from "@/types/academicYear";
import {
  EXAM_TYPES,
  type Exam,
  type ExamInput,
  type ExamType,
  type GradeScale,
} from "@/types/exam";

interface Draft {
  name: string;
  type: ExamType;
  academic_year_id: string;
  grade_scale_id: string;
  start_date: string;
  end_date: string;
}

function toDraft(
  exam: Exam | null,
  years: AcademicYear[],
  scales: GradeScale[],
): Draft {
  if (exam) {
    return {
      name: exam.name,
      type: exam.type,
      academic_year_id: String(exam.academic_year?.id ?? ""),
      grade_scale_id: String(exam.grade_scale?.id ?? ""),
      start_date: exam.start_date,
      end_date: exam.end_date ?? "",
    };
  }
  return {
    name: "",
    type: "half_yearly",
    academic_year_id: String(years.find((y) => y.is_current)?.id ?? ""),
    grade_scale_id: scales.length === 1 ? String(scales[0].id) : "",
    start_date: "",
    end_date: "",
  };
}

/**
 * Exam header fields, for creating (year chosen here, fixed afterwards)
 * and editing. Server field errors show under each field.
 */
export function ExamForm({
  exam,
  years,
  scales,
  submitLabel,
  onSubmit,
  onCancel,
}: {
  exam: Exam | null;
  years: AcademicYear[];
  scales: GradeScale[];
  submitLabel: string;
  onSubmit: (input: ExamInput) => Promise<void>;
  onCancel?: () => void;
}) {
  const [draft, setDraft] = useState<Draft>(() => toDraft(exam, years, scales));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function set<K extends keyof Draft>(key: K, value: Draft[K]) {
    setDraft((d) => ({ ...d, [key]: value }));
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setErrors({});
    try {
      await onSubmit({
        name: draft.name.trim(),
        type: draft.type,
        ...(exam ? {} : { academic_year_id: Number(draft.academic_year_id) }),
        grade_scale_id: Number(draft.grade_scale_id),
        start_date: draft.start_date,
        end_date: draft.end_date || null,
      });
      if (!exam) setDraft(toDraft(null, years, scales));
    } catch (err) {
      // Field errors show under their fields; anything else goes on top.
      if (err instanceof ApiRequestError && err.error.errors) {
        setErrors(
          Object.fromEntries(
            Object.entries(err.error.errors).map(([k, v]) => [k, v[0]]),
          ),
        );
      } else {
        setError(errorMessage(err));
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
      {error && (
        <p role="alert" className="alert alert-error sm:col-span-2">
          {error}
        </p>
      )}
      <FormField label="Name" error={errors.name}>
        <input
          required
          maxLength={100}
          placeholder="e.g. Half Yearly 2026"
          value={draft.name}
          onChange={(e) => set("name", e.target.value)}
          className={fieldInputClass}
        />
      </FormField>
      <FormField label="Type" error={errors.type}>
        <select
          value={draft.type}
          onChange={(e) => set("type", e.target.value as ExamType)}
          className={fieldInputClass}
        >
          {EXAM_TYPES.map((t) => (
            <option key={t.value} value={t.value}>
              {t.label}
            </option>
          ))}
        </select>
      </FormField>
      <FormField
        label="Academic year"
        hint={exam ? "Fixed once the exam is created." : undefined}
        error={errors.academic_year_id}
      >
        <select
          required
          disabled={exam !== null}
          value={draft.academic_year_id}
          onChange={(e) => set("academic_year_id", e.target.value)}
          className={fieldInputClass}
        >
          <option value="">Choose…</option>
          {years.map((y) => (
            <option key={y.id} value={y.id}>
              {y.name}
              {y.is_current ? " (current)" : ""}
            </option>
          ))}
        </select>
      </FormField>
      <FormField
        label="Grade scale"
        hint={scales.length === 0 ? "Create a grade scale first." : undefined}
        error={errors.grade_scale_id}
      >
        <select
          required
          value={draft.grade_scale_id}
          onChange={(e) => set("grade_scale_id", e.target.value)}
          className={fieldInputClass}
        >
          <option value="">Choose…</option>
          {scales.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
      </FormField>
      <FormField label="Start date" error={errors.start_date}>
        <input
          type="date"
          required
          value={draft.start_date}
          onChange={(e) => set("start_date", e.target.value)}
          className={fieldInputClass}
        />
      </FormField>
      <FormField
        label="End date"
        hint="Leave blank for a one-day exam."
        error={errors.end_date}
      >
        <input
          type="date"
          min={draft.start_date || undefined}
          value={draft.end_date}
          onChange={(e) => set("end_date", e.target.value)}
          className={fieldInputClass}
        />
      </FormField>
      <div className="flex gap-2 sm:col-span-2">
        <button type="submit" disabled={busy} className="btn btn-primary">
          {busy ? "Saving…" : submitLabel}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="btn btn-secondary"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  );
}

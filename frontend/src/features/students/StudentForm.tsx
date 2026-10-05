"use client";

import { useState, type FormEvent } from "react";
import { FormField, fieldInputClass } from "@/components/FormField";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import {
  BLOOD_GROUPS,
  GENDERS,
  type BloodGroup,
  type Gender,
  type Student,
  type StudentInput,
} from "@/types/student";

type Draft = Record<
  | "student_id"
  | "admission_no"
  | "admission_date"
  | "name"
  | "dob"
  | "father_name"
  | "mother_name"
  | "phone"
  | "address"
  | "previous_school",
  string
> & { gender: Gender | ""; blood_group: BloodGroup | "" };

const inputClass = fieldInputClass;

const orNull = (value: string) => value.trim() || null;

function toDraft(s?: Student): Draft {
  return {
    student_id: s?.student_id ?? "",
    admission_no: s?.admission_no ?? "",
    admission_date: s?.admission_date ?? "",
    name: s?.name ?? "",
    dob: s?.dob ?? "",
    gender: s?.gender ?? "",
    blood_group: s?.blood_group ?? "",
    father_name: s?.father_name ?? "",
    mother_name: s?.mother_name ?? "",
    phone: s?.phone ?? "",
    address: s?.address ?? "",
    previous_school: s?.previous_school ?? "",
  };
}

function toInput(d: Draft): StudentInput {
  return {
    student_id: orNull(d.student_id),
    admission_no: orNull(d.admission_no),
    admission_date: orNull(d.admission_date),
    name: d.name.trim(),
    dob: d.dob,
    gender: d.gender as Gender,
    blood_group: d.blood_group || null,
    father_name: orNull(d.father_name),
    mother_name: orNull(d.mother_name),
    phone: orNull(d.phone),
    address: orNull(d.address),
    previous_school: orNull(d.previous_school),
  };
}

interface StudentFormProps {
  /** Present when editing. */
  student?: Student;
  submitLabel: string;
  onSubmit: (input: StudentInput) => Promise<void>;
  onCancel?: () => void;
}

/** Create/edit form with per-field server errors. */
export function StudentForm({
  student,
  submitLabel,
  onSubmit,
  onCancel,
}: StudentFormProps) {
  const creating = student === undefined;
  const [draft, setDraft] = useState<Draft>(() => toDraft(student));
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (key: keyof Draft) => (value: string) =>
    setDraft((d) => ({ ...d, [key]: value }));

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setFieldErrors({});
    try {
      await onSubmit(toInput(draft));
    } catch (err) {
      if (err instanceof ApiRequestError && err.error.errors) {
        setFieldErrors(err.error.errors);
        setError("Please correct the highlighted fields.");
      } else {
        setError(errorMessage(err));
      }
    } finally {
      setBusy(false);
    }
  }

  const text = (name: keyof Draft, props: Record<string, unknown> = {}) => (
    <input
      value={draft[name]}
      onChange={(e) => set(name)(e.target.value)}
      aria-invalid={fieldErrors[name] ? true : undefined}
      className={`${inputClass} ${fieldErrors[name] ? "border-red-500" : ""}`}
      {...props}
    />
  );

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}

      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-sm font-semibold">Student</legend>
        <FormField error={fieldErrors.name?.[0]} label="Full name" wide>
          {text("name", { required: true, maxLength: 100 })}
        </FormField>
        <FormField error={fieldErrors.dob?.[0]} label="Date of birth">
          {text("dob", { type: "date", required: true })}
        </FormField>
        <FormField error={fieldErrors.gender?.[0]} label="Gender">
          <select
            required
            value={draft.gender}
            onChange={(e) => set("gender")(e.target.value)}
            className={inputClass}
          >
            <option value="">Choose…</option>
            {GENDERS.map((g) => (
              <option key={g.value} value={g.value}>
                {g.label}
              </option>
            ))}
          </select>
        </FormField>
        <FormField
          error={fieldErrors.blood_group?.[0]}
          label="Blood group (optional)"
        >
          <select
            value={draft.blood_group}
            onChange={(e) => set("blood_group")(e.target.value)}
            className={inputClass}
          >
            <option value="">Unknown</option>
            {BLOOD_GROUPS.map((g) => (
              <option key={g.value} value={g.value}>
                {g.label}
              </option>
            ))}
          </select>
        </FormField>
        <FormField error={fieldErrors.phone?.[0]} label="Phone (optional)">
          {text("phone", { type: "tel", maxLength: 20 })}
        </FormField>
        <FormField
          error={fieldErrors.address?.[0]}
          label="Address (optional)"
          wide
        >
          {text("address", { maxLength: 500 })}
        </FormField>
      </fieldset>

      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-sm font-semibold">Parents</legend>
        <FormField
          error={fieldErrors.father_name?.[0]}
          label="Father's name (optional)"
          hint="As printed on certificates. Link guardians on the profile."
        >
          {text("father_name", { maxLength: 100 })}
        </FormField>
        <FormField
          error={fieldErrors.mother_name?.[0]}
          label="Mother's name (optional)"
        >
          {text("mother_name", { maxLength: 100 })}
        </FormField>
      </fieldset>

      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-sm font-semibold">Admission</legend>
        <FormField
          error={fieldErrors.admission_date?.[0]}
          label={creating ? "Admission date (optional)" : "Admission date"}
          hint={creating ? "Defaults to today." : undefined}
        >
          {text("admission_date", { type: "date", required: !creating })}
        </FormField>
        <FormField
          error={fieldErrors.previous_school?.[0]}
          label="Previous school (optional)"
        >
          {text("previous_school", { maxLength: 150 })}
        </FormField>
        <FormField
          error={fieldErrors.admission_no?.[0]}
          label={creating ? "Admission no. (optional)" : "Admission no."}
          hint={
            creating
              ? "Leave blank to generate (e.g. 2026-0001)."
              : "Change only to correct a mistake."
          }
        >
          {text("admission_no", { maxLength: 20, required: !creating })}
        </FormField>
        <FormField
          error={fieldErrors.student_id?.[0]}
          label={creating ? "Student ID (optional)" : "Student ID"}
          hint={
            creating
              ? "Leave blank to generate. Type one in only when migrating existing records."
              : "Printed on ID cards and used for results lookup."
          }
        >
          {text("student_id", { maxLength: 20, required: !creating })}
        </FormField>
      </fieldset>

      <div className="flex gap-3">
        <button
          type="submit"
          disabled={busy}
          className="rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
        >
          {busy ? "Saving…" : submitLabel}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="text-sm text-gray-500 hover:underline"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  );
}

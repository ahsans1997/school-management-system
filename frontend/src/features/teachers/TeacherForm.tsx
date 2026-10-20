"use client";

import { useState, type FormEvent } from "react";
import { FormField, fieldInputClass } from "@/components/FormField";
import { errorMessage } from "@/lib/errors";
import { ApiRequestError } from "@/services/apiClient";
import type { Teacher, TeacherInput } from "@/types/teacher";

type Draft = Record<keyof TeacherInput, string>;

const orNull = (value: string) => value.trim() || null;

function toDraft(t?: Teacher): Draft {
  return {
    employee_id: t?.employee_id ?? "",
    name: t?.name ?? "",
    designation: t?.designation ?? "",
    department: t?.department ?? "",
    qualification: t?.qualification ?? "",
    joining_date: t?.joining_date ?? "",
    phone: t?.phone ?? "",
    email: t?.email ?? "",
    address: t?.address ?? "",
    salary: t?.salary ?? "",
  };
}

function toInput(d: Draft): TeacherInput {
  return {
    employee_id: orNull(d.employee_id),
    name: d.name.trim(),
    designation: orNull(d.designation),
    department: orNull(d.department),
    qualification: orNull(d.qualification),
    joining_date: d.joining_date,
    phone: d.phone.trim(),
    email: orNull(d.email),
    address: orNull(d.address),
    // Sent as the typed string: the API stores DECIMAL(15,2), never a float.
    salary: orNull(d.salary),
  };
}

interface TeacherFormProps {
  /** Present when editing. */
  teacher?: Teacher;
  submitLabel: string;
  onSubmit: (input: TeacherInput) => Promise<void>;
  onCancel?: () => void;
}

/** Create/edit form with per-field server errors. */
export function TeacherForm({
  teacher,
  submitLabel,
  onSubmit,
  onCancel,
}: TeacherFormProps) {
  const creating = teacher === undefined;
  const [draft, setDraft] = useState<Draft>(() => toDraft(teacher));
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

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

  const input = (name: keyof Draft, props: Record<string, unknown> = {}) => (
    <input
      value={draft[name]}
      onChange={(e) => setDraft((d) => ({ ...d, [name]: e.target.value }))}
      aria-invalid={fieldErrors[name] ? true : undefined}
      className={`${fieldInputClass} ${fieldErrors[name] ? "border-red-500" : ""}`}
      {...props}
    />
  );
  const err = (name: keyof Draft) => fieldErrors[name]?.[0];

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {error && (
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      )}

      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-sm font-semibold">Teacher</legend>
        <FormField label="Full name" error={err("name")} wide>
          {input("name", { required: true, maxLength: 100 })}
        </FormField>
        <FormField label="Phone" error={err("phone")}>
          {input("phone", { type: "tel", required: true, maxLength: 20 })}
        </FormField>
        <FormField label="Email (optional)" error={err("email")}>
          {input("email", { type: "email", maxLength: 255 })}
        </FormField>
        <FormField label="Address (optional)" error={err("address")} wide>
          {input("address", { maxLength: 500 })}
        </FormField>
      </fieldset>

      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-sm font-semibold">Employment</legend>
        <FormField
          label="Designation (optional)"
          hint="e.g. Assistant Teacher"
          error={err("designation")}
        >
          {input("designation", { maxLength: 100 })}
        </FormField>
        <FormField
          label="Department (optional)"
          hint="e.g. Science"
          error={err("department")}
        >
          {input("department", { maxLength: 100 })}
        </FormField>
        <FormField
          label="Qualification (optional)"
          error={err("qualification")}
          wide
        >
          {input("qualification", { maxLength: 150 })}
        </FormField>
        <FormField label="Joining date" error={err("joining_date")}>
          {input("joining_date", { type: "date", required: true })}
        </FormField>
        <FormField
          label="Monthly salary (optional)"
          hint="Visible to staff who can view teachers; never shown publicly."
          error={err("salary")}
        >
          {input("salary", {
            type: "number",
            min: 0,
            step: "0.01",
            inputMode: "decimal",
          })}
        </FormField>
        <FormField
          label={creating ? "Employee ID (optional)" : "Employee ID"}
          hint={
            creating
              ? "Leave blank to generate (e.g. T-0001)."
              : "Change only to correct a mistake."
          }
          error={err("employee_id")}
        >
          {input("employee_id", { maxLength: 20, required: !creating })}
        </FormField>
      </fieldset>

      <div className="flex gap-3">
        <button type="submit" disabled={busy} className="btn btn-primary">
          {busy ? "Saving…" : submitLabel}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="text-sm text-slate-500 hover:underline"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  );
}

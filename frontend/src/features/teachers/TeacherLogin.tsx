"use client";

import { useState, type FormEvent } from "react";
import { fieldInputClass } from "@/components/FormField";
import { errorMessage } from "@/lib/errors";
import * as teacherService from "@/services/teacherService";
import type { Teacher } from "@/types/teacher";

interface Props {
  teacher: Teacher;
  /** teacher.edit and not archived. */
  canCreate: boolean;
  onChange: (teacher: Teacher) => void;
}

/** The teacher's portal login: its status, or a form to create one (Teacher role, fixed server-side). */
export function TeacherLogin({ teacher, canCreate, onChange }: Props) {
  const [open, setOpen] = useState(false);
  const [email, setEmail] = useState(teacher.email ?? "");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const account = teacher.account;

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      onChange(
        await teacherService.createAccount(teacher.id, {
          email: email.trim(),
          password,
        }),
      );
      setOpen(false);
      setPassword("");
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
      <h2 className="mb-3 text-sm font-semibold">Portal login</h2>
      {account ? (
        <p className="text-sm">
          {account.email}{" "}
          {account.is_active ? (
            <span className="text-gray-500">· active</span>
          ) : (
            <span className="text-red-600">
              · disabled (reactivate under Users)
            </span>
          )}
        </p>
      ) : !open ? (
        <div className="flex items-center gap-3 text-sm">
          <span className="text-gray-500">No login yet.</span>
          {canCreate && (
            <button
              type="button"
              onClick={() => setOpen(true)}
              className="hover:underline"
            >
              Create login
            </button>
          )}
        </div>
      ) : (
        <form
          onSubmit={handleSubmit}
          aria-label="Create teacher login"
          className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
        >
          {error && (
            <p role="alert" className="text-sm text-red-600 sm:col-span-3">
              {error}
            </p>
          )}
          <label className="text-sm">
            <span className="mb-1 block font-medium">Login email</span>
            <input
              type="email"
              required
              maxLength={255}
              autoComplete="off"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className={fieldInputClass}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block font-medium">Initial password</span>
            <input
              type="password"
              required
              minLength={8}
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className={fieldInputClass}
            />
          </label>
          <div className="flex gap-3">
            <button
              type="submit"
              disabled={busy}
              className="rounded-md bg-gray-900 px-3 py-1.5 text-sm text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
            >
              Create
            </button>
            <button
              type="button"
              onClick={() => setOpen(false)}
              className="text-sm text-gray-500 hover:underline"
            >
              Cancel
            </button>
          </div>
        </form>
      )}
    </section>
  );
}

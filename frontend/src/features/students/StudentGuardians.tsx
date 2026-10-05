"use client";

import Link from "next/link";
import { useState, type FormEvent } from "react";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as guardianService from "@/services/guardianService";
import * as studentService from "@/services/studentService";
import type { Guardian } from "@/types/guardian";
import {
  RELATIONSHIPS,
  type GuardianLink,
  type Relationship,
  type Student,
} from "@/types/student";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

interface Props {
  student: Student;
  /** student.edit and not archived. */
  canEdit: boolean;
  /** guardian.view — needed to search the guardian register. */
  canSearch: boolean;
  onChange: (student: Student) => void;
}

function RelationshipSelect({
  value,
  onChange,
  label,
}: {
  value: Relationship | "";
  onChange: (value: Relationship) => void;
  label: string;
}) {
  return (
    <select
      aria-label={label}
      required
      value={value}
      onChange={(e) => onChange(e.target.value as Relationship)}
      className={inputClass}
    >
      <option value="">Relationship…</option>
      {RELATIONSHIPS.map((r) => (
        <option key={r.value} value={r.value}>
          {r.label}
        </option>
      ))}
    </select>
  );
}

/** The student's guardians: primary first; link from the register, re-label, promote, unlink. */
export function StudentGuardians({
  student,
  canEdit,
  canSearch,
  onChange,
}: Props) {
  const links = student.guardians ?? [];
  const [search, setSearch] = useState("");
  const [results, setResults] = useState<Guardian[] | null>(null);
  const [picked, setPicked] = useState<Guardian | null>(null);
  const [relationship, setRelationship] = useState<Relationship | "">("");
  const [makePrimary, setMakePrimary] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run(task: () => Promise<Student>) {
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

  async function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setPicked(null);
    try {
      const res = await guardianService.listGuardians(1, search.trim(), 10);
      setResults(res.data);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function handleLink(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!picked || !relationship) return;
    const ok = await run(() =>
      studentService.linkGuardian(
        student.id,
        picked.id,
        relationship,
        makePrimary,
      ),
    );
    if (ok) {
      setPicked(null);
      setResults(null);
      setSearch("");
      setRelationship("");
      setMakePrimary(false);
    }
  }

  function unlink(link: GuardianLink) {
    if (!window.confirm(`Unlink ${link.name} from ${student.name}?`)) return;
    void run(() => studentService.unlinkGuardian(student.id, link.id));
  }

  const linkedIds = new Set(links.map((l) => l.id));

  return (
    <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
      <h2 className="mb-3 text-sm font-semibold">Guardians</h2>

      {error && (
        <p role="alert" className="mb-3 text-sm text-red-600">
          {error}
        </p>
      )}

      {links.length === 0 ? (
        <p className="mb-3 text-sm text-gray-500">No guardians linked yet.</p>
      ) : (
        <ul className="mb-4 divide-y divide-gray-100 dark:divide-gray-800">
          {links.map((link) => (
            <li
              key={link.id}
              className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm"
            >
              <span className="min-w-0 flex-1">
                <span className="font-medium">{link.name}</span>
                {link.is_primary && (
                  <span className="ml-2 rounded bg-gray-900 px-1.5 py-0.5 text-xs text-white dark:bg-gray-100 dark:text-gray-900">
                    Primary
                  </span>
                )}
                <span className="block text-xs text-gray-500 tabular-nums">
                  {link.phone}
                </span>
              </span>
              {canEdit ? (
                <>
                  <RelationshipSelect
                    label={`Relationship of ${link.name}`}
                    value={link.relationship}
                    onChange={(value) =>
                      void run(() =>
                        studentService.updateGuardianLink(
                          student.id,
                          link.id,
                          value,
                        ),
                      )
                    }
                  />
                  {!link.is_primary && (
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() =>
                        void run(() =>
                          studentService.updateGuardianLink(
                            student.id,
                            link.id,
                            link.relationship,
                            true,
                          ),
                        )
                      }
                      className="text-xs hover:underline"
                    >
                      Make primary
                    </button>
                  )}
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => unlink(link)}
                    className="text-xs text-red-600 hover:underline"
                  >
                    Unlink
                  </button>
                </>
              ) : (
                <span className="text-gray-500">
                  {labelOf(RELATIONSHIPS, link.relationship)}
                </span>
              )}
            </li>
          ))}
        </ul>
      )}

      {canEdit && canSearch && (
        <div className="border-t border-gray-100 pt-3 dark:border-gray-800">
          <form
            role="search"
            onSubmit={handleSearch}
            className="flex flex-wrap gap-2"
          >
            <input
              type="search"
              aria-label="Find a guardian"
              placeholder="Find a guardian by name or phone"
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

          {results !== null && (
            <ul className="mt-2 text-sm">
              {results.length === 0 && (
                <li className="py-1 text-gray-500">
                  No match.{" "}
                  <Link href="/admin/guardians" className="underline">
                    Add the guardian first
                  </Link>
                  , then link them here.
                </li>
              )}
              {results.map((g) => (
                <li key={g.id} className="flex items-center gap-2 py-1">
                  <span className="flex-1">
                    {g.name}{" "}
                    <span className="text-xs text-gray-500 tabular-nums">
                      {g.phone}
                    </span>
                  </span>
                  {linkedIds.has(g.id) ? (
                    <span className="text-xs text-gray-500">Linked</span>
                  ) : (
                    <button
                      type="button"
                      onClick={() => setPicked(g)}
                      className="text-xs hover:underline"
                    >
                      Select
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}

          {picked && (
            <form
              onSubmit={handleLink}
              aria-label="Link guardian"
              className="mt-3 flex flex-wrap items-center gap-2 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-900"
            >
              <span className="font-medium">{picked.name}</span>
              <RelationshipSelect
                label="Relationship"
                value={relationship}
                onChange={setRelationship}
              />
              <label className="flex items-center gap-1">
                <input
                  type="checkbox"
                  checked={makePrimary || links.length === 0}
                  disabled={links.length === 0}
                  onChange={(e) => setMakePrimary(e.target.checked)}
                />
                Primary
              </label>
              <button
                type="submit"
                disabled={busy || !relationship}
                className="rounded-md bg-gray-900 px-3 py-1 text-white disabled:opacity-60 dark:bg-gray-100 dark:text-gray-900"
              >
                Link
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
    </section>
  );
}

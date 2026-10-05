"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { DetailsList } from "@/components/DetailsList";
import { DocumentsPanel } from "@/components/DocumentsPanel";
import { PhotoEditor } from "@/components/PhotoEditor";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatDay } from "@/lib/format";
import { ApiRequestError } from "@/services/apiClient";
import * as teacherService from "@/services/teacherService";
import type { Teacher } from "@/types/teacher";
import { TeacherLogin } from "./TeacherLogin";
import { TeacherRoutine } from "./TeacherRoutine";
import { TeacherTeaching } from "./TeacherTeaching";

const isNotFound = (err: unknown) =>
  err instanceof ApiRequestError && err.error.status === 404;

const money = new Intl.NumberFormat("en", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

/** A teacher's record: details, photo, login, teaching, documents, archive/restore. */
export function TeacherProfile({ teacherId }: { teacherId: number }) {
  const { can } = useAuth();
  const [teacher, setTeacher] = useState<Teacher | null>(null);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    teacherService
      .getTeacher(teacherId)
      .then((t) => {
        if (active) setTeacher(t);
      })
      .catch((err) => {
        if (!active) return;
        if (isNotFound(err)) setMissing(true);
        else setError(errorMessage(err));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [teacherId]);

  /** Merge, so partial responses keep what's already loaded. */
  const merge = (next: Partial<Teacher>) =>
    setTeacher((prev) => (prev ? { ...prev, ...next } : prev));

  async function run(task: () => Promise<Partial<Teacher>>, success: string) {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      merge(await task());
      setNotice(success);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const backLink = (
    <Link
      href="/admin/teachers"
      className="text-sm text-gray-500 hover:underline dark:text-gray-400"
    >
      ← Teachers
    </Link>
  );

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }
  if (missing || !teacher) {
    return (
      <div className="mx-auto max-w-4xl p-6">
        {backLink}
        <p className="mt-4 text-sm">{error ?? "This teacher doesn't exist."}</p>
      </div>
    );
  }

  const archived = teacher.archived_at !== null;
  const canEdit = can("teacher.edit") && !archived;
  // The pickers read the class catalogue, sections and class subjects.
  const canAssign =
    canEdit && can("class.view") && can("section.view") && can("subject.view");

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      {backLink}

      {archived && (
        <p className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
          Archived on {formatDay(teacher.archived_at?.slice(0, 10))}. The record
          is read-only until restored.
        </p>
      )}
      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}
      {notice && <p className="text-sm text-green-600">{notice}</p>}

      <header className="flex flex-wrap items-start gap-4">
        <PhotoEditor
          photoUrl={teacher.photo_url}
          alt={`Photo of ${teacher.name}`}
          canEdit={canEdit}
          busy={busy}
          onError={setError}
          onUpload={(file) =>
            void run(
              () => teacherService.uploadPhoto(teacher.id, file),
              "Photo updated.",
            )
          }
          onRemove={() =>
            void run(
              () => teacherService.removePhoto(teacher.id),
              "Photo removed.",
            )
          }
        />

        <div className="min-w-0 flex-1">
          <h1 className="text-2xl font-semibold">{teacher.name}</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            <span className="tabular-nums">{teacher.employee_id}</span>
            {teacher.designation && ` · ${teacher.designation}`}
            {teacher.department && ` · ${teacher.department}`}
          </p>
          <div className="mt-3 flex flex-wrap gap-3 text-sm">
            {canEdit && (
              <Link
                href={`/admin/teachers/${teacher.id}/edit`}
                className="rounded-md border border-gray-300 px-3 py-1 dark:border-gray-700"
              >
                Edit details
              </Link>
            )}
            {can("teacher.delete") &&
              (archived ? (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() =>
                    void run(
                      () => teacherService.restoreTeacher(teacher.id),
                      `${teacher.name} restored.`,
                    )
                  }
                  className="rounded-md border border-gray-300 px-3 py-1 dark:border-gray-700"
                >
                  Restore
                </button>
              ) : (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => {
                    if (
                      !window.confirm(
                        `Archive ${teacher.name}? Their login is switched off; the record is kept and can be restored.`,
                      )
                    )
                      return;
                    void run(async () => {
                      await teacherService.archiveTeacher(teacher.id);
                      return {
                        archived_at: new Date().toISOString(),
                        account: teacher.account
                          ? { ...teacher.account, is_active: false }
                          : teacher.account,
                      };
                    }, `${teacher.name} archived.`);
                  }}
                  className="rounded-md border border-red-300 px-3 py-1 text-red-600 dark:border-red-900"
                >
                  Archive
                </button>
              ))}
          </div>
        </div>
      </header>

      <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
        <h2 className="mb-3 text-sm font-semibold">Details</h2>
        <DetailsList
          rows={[
            ["Joining date", formatDay(teacher.joining_date)],
            ["Phone", teacher.phone],
            ["Email", teacher.email],
            ["Address", teacher.address],
            ["Qualification", teacher.qualification],
            [
              "Monthly salary",
              teacher.salary === null
                ? null
                : money.format(Number(teacher.salary)),
            ],
          ]}
        />
      </section>

      <TeacherTeaching
        teacher={teacher}
        canAssign={canAssign}
        onChange={(next) =>
          merge({
            assignments: next.assignments,
            class_teacher_of: next.class_teacher_of,
          })
        }
      />

      {can("routine.view") && <TeacherRoutine teacherId={teacher.id} />}

      <TeacherLogin
        teacher={teacher}
        canCreate={canEdit}
        onChange={(next) => merge({ account: next.account })}
      />

      <DocumentsPanel
        documents={teacher.documents ?? []}
        canEdit={canEdit}
        upload={(title, file) =>
          teacherService.uploadDocument(teacher.id, title, file)
        }
        remove={(doc) => teacherService.deleteDocument(teacher.id, doc.id)}
        onChange={(documents) => merge({ documents })}
      />
    </div>
  );
}

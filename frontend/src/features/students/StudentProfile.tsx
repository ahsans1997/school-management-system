"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { DetailsList } from "@/components/DetailsList";
import { DocumentsPanel } from "@/components/DocumentsPanel";
import { PhotoEditor } from "@/components/PhotoEditor";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatDay } from "@/lib/format";
import { labelOf } from "@/lib/options";
import { ApiRequestError } from "@/services/apiClient";
import * as studentService from "@/services/studentService";
import { BLOOD_GROUPS, GENDERS, type Student } from "@/types/student";
import { StudentGuardians } from "./StudentGuardians";

const isNotFound = (err: unknown) =>
  err instanceof ApiRequestError && err.error.status === 404;

/** A student's record: details, photo, guardians, documents, archive/restore. */
export function StudentProfile({ studentId }: { studentId: number }) {
  const { can } = useAuth();
  const [student, setStudent] = useState<Student | null>(null);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Initial fetch: state is set only in promise callbacks, and ignored after unmount.
  useEffect(() => {
    let active = true;
    studentService
      .getStudent(studentId)
      .then((s) => {
        if (active) setStudent(s);
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
  }, [studentId]);

  /** Merge, so responses without guardians/documents keep the loaded ones. */
  const merge = (next: Partial<Student>) =>
    setStudent((prev) => (prev ? { ...prev, ...next } : prev));

  async function run(task: () => Promise<Partial<Student>>, success: string) {
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
      href="/admin/students"
      className="text-sm text-gray-500 hover:underline dark:text-gray-400"
    >
      ← Students
    </Link>
  );

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }
  if (missing || !student) {
    return (
      <div className="mx-auto max-w-4xl p-6">
        {backLink}
        <p className="mt-4 text-sm">{error ?? "This student doesn't exist."}</p>
      </div>
    );
  }

  const archived = student.archived_at !== null;
  const canEdit = can("student.edit") && !archived;

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      {backLink}

      {archived && (
        <p className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
          Archived on {formatDay(student.archived_at?.slice(0, 10))}. The record
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
          photoUrl={student.photo_url}
          alt={`Photo of ${student.name}`}
          canEdit={canEdit}
          busy={busy}
          onError={setError}
          onUpload={(file) =>
            void run(
              () => studentService.uploadPhoto(student.id, file),
              "Photo updated.",
            )
          }
          onRemove={() =>
            void run(
              () => studentService.removePhoto(student.id),
              "Photo removed.",
            )
          }
        />

        <div className="min-w-0 flex-1">
          <h1 className="text-2xl font-semibold">{student.name}</h1>
          <p className="text-sm text-gray-500 tabular-nums dark:text-gray-400">
            Student ID {student.student_id} · Admission no.{" "}
            {student.admission_no}
          </p>
          <div className="mt-3 flex flex-wrap gap-3 text-sm">
            {canEdit && (
              <Link
                href={`/admin/students/${student.id}/edit`}
                className="rounded-md border border-gray-300 px-3 py-1 dark:border-gray-700"
              >
                Edit details
              </Link>
            )}
            {can("student.delete") &&
              (archived ? (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() =>
                    void run(
                      () => studentService.restoreStudent(student.id),
                      `${student.name} restored.`,
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
                        `Archive ${student.name}? The record is kept and can be restored.`,
                      )
                    )
                      return;
                    void run(async () => {
                      await studentService.archiveStudent(student.id);
                      return { archived_at: new Date().toISOString() };
                    }, `${student.name} archived.`);
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
            ["Date of birth", formatDay(student.dob)],
            ["Gender", labelOf(GENDERS, student.gender)],
            ["Blood group", labelOf(BLOOD_GROUPS, student.blood_group)],
            ["Phone", student.phone],
            ["Address", student.address],
            ["Father's name", student.father_name],
            ["Mother's name", student.mother_name],
            ["Admission date", formatDay(student.admission_date)],
            ["Previous school", student.previous_school],
          ]}
        />
      </section>

      <StudentGuardians
        student={student}
        canEdit={canEdit}
        canSearch={can("guardian.view")}
        onChange={(next) => merge({ guardians: next.guardians })}
      />

      <DocumentsPanel
        documents={student.documents ?? []}
        canEdit={canEdit}
        upload={(title, file) =>
          studentService.uploadDocument(student.id, title, file)
        }
        remove={(doc) => studentService.deleteDocument(student.id, doc.id)}
        onChange={(documents) => merge({ documents })}
      />
    </div>
  );
}

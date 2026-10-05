"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import * as studentService from "@/services/studentService";
import type { Student } from "@/types/student";
import { StudentForm } from "./StudentForm";

/** Admit a new student, or edit one (`studentId`). Lands on the profile afterwards. */
export function StudentEditor({ studentId }: { studentId?: number }) {
  const router = useRouter();
  const editing = studentId !== undefined;
  const [student, setStudent] = useState<Student | null>(null);
  const [loading, setLoading] = useState(editing);
  const [error, setError] = useState<string | null>(null);

  // Initial fetch (edit only): state is set only in promise callbacks.
  useEffect(() => {
    if (studentId === undefined) return;
    let active = true;
    studentService
      .getStudent(studentId)
      .then((s) => {
        if (active) setStudent(s);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [studentId]);

  const back = editing ? `/admin/students/${studentId}` : "/admin/students";

  if (loading) {
    return <p className="p-8 text-sm text-gray-500">Loading…</p>;
  }

  return (
    <div className="mx-auto max-w-3xl p-6">
      <Link
        href={back}
        className="text-sm text-gray-500 hover:underline dark:text-gray-400"
      >
        ← {editing ? "Back to profile" : "Students"}
      </Link>
      <h1 className="mt-2 mb-6 text-2xl font-semibold">
        {editing ? `Edit ${student?.name ?? "student"}` : "Add a student"}
      </h1>

      {error ? (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      ) : editing && student?.archived_at ? (
        <p className="text-sm">
          This student is archived. Restore the record to edit it.
        </p>
      ) : (
        <StudentForm
          student={student ?? undefined}
          submitLabel={editing ? "Save changes" : "Add student"}
          onCancel={() => router.push(back)}
          onSubmit={async (input) => {
            const saved =
              studentId !== undefined
                ? await studentService.updateStudent(studentId, input)
                : await studentService.createStudent(input);
            router.push(`/admin/students/${saved.id}`);
          }}
        />
      )}
    </div>
  );
}

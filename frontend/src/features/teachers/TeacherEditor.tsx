"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import * as teacherService from "@/services/teacherService";
import type { Teacher } from "@/types/teacher";
import { TeacherForm } from "./TeacherForm";
import { PageLoader } from "@/components/Loading";

/** Add a teacher, or edit one (`teacherId`). Lands on the profile afterwards. */
export function TeacherEditor({ teacherId }: { teacherId?: number }) {
  const router = useRouter();
  const editing = teacherId !== undefined;
  const [teacher, setTeacher] = useState<Teacher | null>(null);
  const [loading, setLoading] = useState(editing);
  const [error, setError] = useState<string | null>(null);

  // Initial fetch (edit only): state is set only in promise callbacks.
  useEffect(() => {
    if (teacherId === undefined) return;
    let active = true;
    teacherService
      .getTeacher(teacherId)
      .then((t) => {
        if (active) setTeacher(t);
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
  }, [teacherId]);

  const back = editing ? `/admin/teachers/${teacherId}` : "/admin/teachers";

  if (loading) {
    return <PageLoader />;
  }

  return (
    <div className="mx-auto max-w-3xl p-6">
      <Link
        href={back}
        className="text-sm text-slate-500 hover:underline dark:text-slate-400"
      >
        ← {editing ? "Back to profile" : "Teachers"}
      </Link>
      <h1 className="mt-2 mb-6 text-2xl font-semibold">
        {editing ? `Edit ${teacher?.name ?? "teacher"}` : "Add a teacher"}
      </h1>

      {error ? (
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      ) : editing && teacher?.archived_at ? (
        <p className="text-sm">
          This teacher is archived. Restore the record to edit it.
        </p>
      ) : (
        <TeacherForm
          teacher={teacher ?? undefined}
          submitLabel={editing ? "Save changes" : "Add teacher"}
          onCancel={() => router.push(back)}
          onSubmit={async (input) => {
            const saved =
              teacherId !== undefined
                ? await teacherService.updateTeacher(teacherId, input)
                : await teacherService.createTeacher(input);
            router.push(`/admin/teachers/${saved.id}`);
          }}
        />
      )}
    </div>
  );
}

"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { TeacherEditor } from "@/features/teachers/TeacherEditor";

export default function EditTeacherPage({
  params,
}: {
  params: Promise<{ teacherId: string }>;
}) {
  const { teacherId } = use(params);

  return (
    <RequireAuth permission="teacher.edit">
      <TeacherEditor teacherId={Number(teacherId)} />
    </RequireAuth>
  );
}

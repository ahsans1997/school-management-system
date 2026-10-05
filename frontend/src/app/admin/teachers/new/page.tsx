"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { TeacherEditor } from "@/features/teachers/TeacherEditor";

export default function NewTeacherPage() {
  return (
    <RequireAuth permission="teacher.create">
      <TeacherEditor />
    </RequireAuth>
  );
}

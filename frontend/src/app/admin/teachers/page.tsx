"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { TeachersList } from "@/features/teachers/TeachersList";

export default function TeachersPage() {
  return (
    <RequireAuth permission="teacher.view">
      <TeachersList />
    </RequireAuth>
  );
}

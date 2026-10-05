"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { StudentsList } from "@/features/students/StudentsList";

export default function StudentsPage() {
  return (
    <RequireAuth permission="student.view">
      <StudentsList />
    </RequireAuth>
  );
}

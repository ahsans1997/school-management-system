"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { StudentEditor } from "@/features/students/StudentEditor";

export default function NewStudentPage() {
  return (
    <RequireAuth permission="student.create">
      <StudentEditor />
    </RequireAuth>
  );
}

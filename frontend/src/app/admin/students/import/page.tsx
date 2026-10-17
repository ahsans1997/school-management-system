"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { StudentImport } from "@/features/students/StudentImport";

export default function StudentImportPage() {
  return (
    <RequireAuth permission="student.create">
      <StudentImport />
    </RequireAuth>
  );
}

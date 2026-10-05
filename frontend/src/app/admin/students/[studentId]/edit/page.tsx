"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { StudentEditor } from "@/features/students/StudentEditor";

export default function EditStudentPage({
  params,
}: {
  params: Promise<{ studentId: string }>;
}) {
  const { studentId } = use(params);

  return (
    <RequireAuth permission="student.edit">
      <StudentEditor studentId={Number(studentId)} />
    </RequireAuth>
  );
}

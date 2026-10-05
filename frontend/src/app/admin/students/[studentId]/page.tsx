"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { StudentProfile } from "@/features/students/StudentProfile";

export default function StudentPage({
  params,
}: {
  params: Promise<{ studentId: string }>;
}) {
  const { studentId } = use(params);

  return (
    <RequireAuth permission="student.view">
      <StudentProfile studentId={Number(studentId)} />
    </RequireAuth>
  );
}

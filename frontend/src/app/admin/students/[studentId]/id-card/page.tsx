"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { StudentIdCardPage } from "@/features/students/StudentIdCardPage";

export default function StudentIdCardRoute({
  params,
}: {
  params: Promise<{ studentId: string }>;
}) {
  const { studentId } = use(params);

  return (
    <RequireAuth permission="student.view">
      <StudentIdCardPage studentId={Number(studentId)} />
    </RequireAuth>
  );
}

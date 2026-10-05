"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { GradeScalesManager } from "@/features/exams/GradeScalesManager";

export default function GradeScalesPage() {
  return (
    <RequireAuth permission="exam.view">
      <GradeScalesManager />
    </RequireAuth>
  );
}

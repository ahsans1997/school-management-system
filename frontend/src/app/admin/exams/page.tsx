"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { ExamsList } from "@/features/exams/ExamsList";

export default function ExamsPage() {
  return (
    <RequireAuth permission="exam.view">
      <ExamsList />
    </RequireAuth>
  );
}

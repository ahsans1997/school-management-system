"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { ExamDetail } from "@/features/exams/ExamDetail";

export default function ExamPage({
  params,
}: {
  params: Promise<{ examId: string }>;
}) {
  const { examId } = use(params);

  return (
    <RequireAuth permission="exam.view">
      <ExamDetail examId={Number(examId)} />
    </RequireAuth>
  );
}

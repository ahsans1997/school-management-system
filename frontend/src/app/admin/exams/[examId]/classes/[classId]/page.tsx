"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { ExamPapersEditor } from "@/features/exams/ExamPapersEditor";

export default function ExamClassPapersPage({
  params,
}: {
  params: Promise<{ examId: string; classId: string }>;
}) {
  const { examId, classId } = use(params);

  return (
    <RequireAuth permission="exam.view">
      <ExamPapersEditor examId={Number(examId)} classId={Number(classId)} />
    </RequireAuth>
  );
}

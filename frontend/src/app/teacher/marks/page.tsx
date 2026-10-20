"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { MarksPage } from "@/features/marks/MarksPage";

export default function TeacherMarksPage({
  searchParams,
}: {
  searchParams: Promise<{ exam?: string }>;
}) {
  const { exam } = use(searchParams);

  return (
    <RequireAuth permission="exam.marks_entry">
      <MarksPage
        basePath="/teacher/marks"
        initialExamId={exam ? Number(exam) : undefined}
      />
    </RequireAuth>
  );
}

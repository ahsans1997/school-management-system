"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { MarksPage } from "@/features/marks/MarksPage";

export default function AdminMarksPage({
  searchParams,
}: {
  searchParams: Promise<{ exam?: string }>;
}) {
  const { exam } = use(searchParams);

  return (
    <RequireAuth permission="exam.marks_entry">
      <MarksPage
        basePath="/admin/marks"
        initialExamId={exam ? Number(exam) : undefined}
      />
    </RequireAuth>
  );
}

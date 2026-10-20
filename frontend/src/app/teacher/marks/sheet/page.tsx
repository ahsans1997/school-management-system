"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { MarkSheetEditor } from "@/features/marks/MarkSheetEditor";

export default function TeacherMarkSheetPage({
  searchParams,
}: {
  searchParams: Promise<{ exam?: string; paper?: string; section?: string }>;
}) {
  const { exam, paper, section } = use(searchParams);

  return (
    <RequireAuth permission="exam.marks_entry">
      <MarkSheetEditor
        key={`${exam}-${paper}-${section}`}
        examId={Number(exam)}
        paperId={Number(paper)}
        sectionId={Number(section)}
        basePath="/teacher/marks"
      />
    </RequireAuth>
  );
}

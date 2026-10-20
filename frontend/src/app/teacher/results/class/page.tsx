"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { ClassResults } from "@/features/results/ClassResults";

export default function TeacherClassResultsPage({
  searchParams,
}: {
  searchParams: Promise<{ exam?: string; class?: string }>;
}) {
  const { exam, class: classId } = use(searchParams);

  return (
    <RequireAuth permission="result.view">
      <ClassResults
        key={`${exam}-${classId}`}
        examId={Number(exam)}
        classId={Number(classId)}
        basePath="/teacher/results"
      />
    </RequireAuth>
  );
}

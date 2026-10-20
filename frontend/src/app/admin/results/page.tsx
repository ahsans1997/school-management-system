"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { ResultsPage } from "@/features/results/ResultsPage";

export default function AdminResultsPage({
  searchParams,
}: {
  searchParams: Promise<{ exam?: string }>;
}) {
  const { exam } = use(searchParams);

  return (
    <RequireAuth permission="result.view">
      <ResultsPage
        basePath="/admin/results"
        initialExamId={exam ? Number(exam) : undefined}
      />
    </RequireAuth>
  );
}

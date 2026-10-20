"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { ReportCardPage } from "@/features/results/ReportCardPage";

export default function AdminReportCardPage({
  searchParams,
}: {
  searchParams: Promise<{ id?: string }>;
}) {
  const { id } = use(searchParams);

  return (
    <RequireAuth permission="result.view">
      <ReportCardPage
        key={id}
        resultId={Number(id)}
        basePath="/admin/results"
      />
    </RequireAuth>
  );
}

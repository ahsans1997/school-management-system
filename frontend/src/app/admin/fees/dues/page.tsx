"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { DueReport } from "@/features/fees/DueReport";

export default function DueReportRoute() {
  return (
    <RequireAuth permission="fee.report">
      <DueReport />
    </RequireAuth>
  );
}

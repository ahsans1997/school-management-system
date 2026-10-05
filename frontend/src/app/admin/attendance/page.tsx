"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { AttendancePage } from "@/features/attendance/AttendancePage";

export default function AdminAttendancePage({
  searchParams,
}: {
  searchParams: Promise<{ section?: string }>;
}) {
  const { section } = use(searchParams);

  return (
    <RequireAuth permission="attendance.view">
      <AttendancePage
        initialSectionId={section ? Number(section) : undefined}
      />
    </RequireAuth>
  );
}

"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { EnrollmentsManager } from "@/features/enrollments/EnrollmentsManager";

export default function EnrollmentsPage() {
  return (
    <RequireAuth permission="enrollment.view">
      <EnrollmentsManager />
    </RequireAuth>
  );
}

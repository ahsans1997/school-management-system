"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { AcademicYearsManager } from "@/features/academic-years/AcademicYearsManager";

export default function AcademicYearsPage() {
  return (
    <RequireAuth permission="academic_year.view">
      <AcademicYearsManager />
    </RequireAuth>
  );
}

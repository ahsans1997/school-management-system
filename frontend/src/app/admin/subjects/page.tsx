"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { SubjectsManager } from "@/features/subjects/SubjectsManager";

export default function SubjectsPage() {
  return (
    <RequireAuth permission="subject.view">
      <SubjectsManager />
    </RequireAuth>
  );
}

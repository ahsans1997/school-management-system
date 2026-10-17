"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { IdCardsPage } from "@/features/students/IdCardsPage";

export default function IdCardsRoute() {
  return (
    <RequireAuth permission="student.view">
      <IdCardsPage />
    </RequireAuth>
  );
}

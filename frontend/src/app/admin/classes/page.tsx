"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { ClassesManager } from "@/features/classes/ClassesManager";

export default function ClassesPage() {
  return (
    <RequireAuth permission="class.view">
      <ClassesManager />
    </RequireAuth>
  );
}

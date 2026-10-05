"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { RoutineBuilder } from "@/features/routines/RoutineBuilder";

export default function RoutinesPage() {
  return (
    <RequireAuth permission="routine.view">
      <RoutineBuilder />
    </RequireAuth>
  );
}

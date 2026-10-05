"use client";

import { useEffect, useState } from "react";
import { RoutineGrid } from "@/components/RoutineGrid";
import { errorMessage } from "@/lib/errors";
import * as routineService from "@/services/routineService";
import type { RoutineSlot } from "@/types/routine";

/** A teacher's week across sections — the same slots as the section routines. */
export function TeacherRoutine({ teacherId }: { teacherId: number }) {
  const [slots, setSlots] = useState<RoutineSlot[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    routineService
      .getTeacherRoutine(teacherId)
      .then((r) => {
        if (active) setSlots(r.slots);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [teacherId]);

  return (
    <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
      <h2 className="mb-3 text-sm font-semibold">Weekly routine</h2>
      {error ? (
        <p className="text-sm text-gray-500">{error}</p>
      ) : slots === null ? (
        <p className="text-sm text-gray-500">Loading…</p>
      ) : (
        <RoutineGrid slots={slots} perspective="teacher" />
      )}
    </section>
  );
}

"use client";

import { useEffect, useState } from "react";
import { RoutineGrid } from "@/components/RoutineGrid";
import { errorMessage } from "@/lib/errors";
import * as routineService from "@/services/routineService";
import type { MyRoutine as MyRoutineData } from "@/types/routine";

/** The caller's own routine: a teacher's week, a student's section, or each child's. */
export function MyRoutine() {
  const [data, setData] = useState<MyRoutineData | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    routineService
      .getMyRoutine()
      .then((d) => {
        if (active) setData(d);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-6">
      <h1 className="text-2xl font-semibold">
        {data?.type === "parent" ? "Class routines" : "My routine"}
        {data && (
          <span className="ml-2 text-base font-normal text-gray-500">
            {data.academic_year.name}
          </span>
        )}
      </h1>

      {error ? (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      ) : !data ? (
        <p className="text-sm text-gray-500">Loading…</p>
      ) : data.type === "teacher" ? (
        data.teacher ? (
          <RoutineGrid slots={data.slots} perspective="teacher" />
        ) : (
          <p className="text-sm text-gray-500">
            Your login isn&apos;t linked to a teacher record yet.
          </p>
        )
      ) : data.type === "none" || data.weeks.length === 0 ? (
        <p className="text-sm text-gray-500">No routine to show.</p>
      ) : (
        data.weeks.map((w) => (
          <section key={w.student.id}>
            <h2 className="mb-2 text-sm font-semibold">
              {data.type === "parent" && `${w.student.name} · `}
              {w.section
                ? `${w.section.class} ${w.section.name}`
                : "Not enrolled this year"}
            </h2>
            {w.section && <RoutineGrid slots={w.slots} perspective="section" />}
          </section>
        ))
      )}
    </div>
  );
}

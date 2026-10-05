"use client";

import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import * as attendanceService from "@/services/attendanceService";
import type { AttendanceSection } from "@/types/attendance";
import { AttendanceTaker } from "./AttendanceTaker";
import { MonthlyAttendance } from "./MonthlyAttendance";

/** Attendance for admins and teachers alike — the API limits teachers to their sections. */
export function AttendancePage({
  initialSectionId,
}: {
  initialSectionId?: number;
}) {
  const [sections, setSections] = useState<AttendanceSection[] | null>(null);
  const [tab, setTab] = useState<"take" | "monthly">("take");
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    attendanceService
      .listSections()
      .then((s) => {
        if (active) setSections(s);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  const tabClass = (on: boolean) =>
    `rounded-md px-3 py-1 text-sm ${on ? "bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900" : "border border-gray-300 dark:border-gray-700"}`;

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Attendance</h1>
      <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
        Daily attendance per section: Present, Absent, Late or Leave.
      </p>

      {error && (
        <p role="alert" className="mb-4 text-sm text-red-600">
          {error}
        </p>
      )}

      <div role="tablist" className="mb-5 flex gap-2">
        <button
          type="button"
          role="tab"
          aria-selected={tab === "take"}
          onClick={() => setTab("take")}
          className={tabClass(tab === "take")}
        >
          Take attendance
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={tab === "monthly"}
          onClick={() => setTab("monthly")}
          className={tabClass(tab === "monthly")}
        >
          Monthly
        </button>
      </div>

      {sections === null ? (
        !error && <p className="text-sm text-gray-500">Loading…</p>
      ) : tab === "take" ? (
        <AttendanceTaker
          sections={sections}
          initialSectionId={initialSectionId}
        />
      ) : (
        <MonthlyAttendance sections={sections} />
      )}
    </div>
  );
}

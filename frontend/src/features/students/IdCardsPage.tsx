"use client";

import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import * as classService from "@/services/classService";
import * as sectionService from "@/services/sectionService";
import * as studentToolsService from "@/services/studentToolsService";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";
import type { IdCardSheet as Sheet } from "@/types/studentTools";
import { IdCardSheet } from "./IdCardSheet";

const inputClass =
  "rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

/** A whole section's ID cards for the current year, ready to print. */
export function IdCardsPage() {
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [classId, setClassId] = useState("");
  const [sections, setSections] = useState<Section[]>([]);
  const [sectionId, setSectionId] = useState("");
  const [sheet, setSheet] = useState<Sheet | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Class options: state set only in promise callbacks.
  useEffect(() => {
    let active = true;
    classService
      .listClasses(1, 100)
      .then((res) => {
        if (active) setClasses(res.data);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  function chooseClass(value: string) {
    setClassId(value);
    setSectionId("");
    setSections([]);
    setSheet(null);
    if (!value) return;
    sectionService
      .listSections(Number(value), 1, 100)
      .then((res) => setSections(res.data))
      .catch((err) => setError(errorMessage(err)));
  }

  function chooseSection(value: string) {
    setSectionId(value);
    setSheet(null);
    setError(null);
    if (!value) return;
    studentToolsService
      .getSectionIdCards(Number(value))
      .then(setSheet)
      .catch((err) => setError(errorMessage(err)));
  }

  return (
    <div className="mx-auto max-w-5xl p-6 print:p-0">
      <div className="print:hidden">
        <h1 className="mb-1 text-2xl font-semibold">ID cards</h1>
        <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
          A section&apos;s cards for the current year, two to a row on A4. A
          single student&apos;s card is on their profile.
        </p>

        {error && (
          <p role="alert" className="mb-4 text-sm text-red-600">
            {error}
          </p>
        )}

        <div className="mb-6 flex flex-wrap items-center gap-2">
          <select
            aria-label="Class"
            value={classId}
            onChange={(e) => chooseClass(e.target.value)}
            className={inputClass}
          >
            <option value="">Class…</option>
            {classes.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          <select
            aria-label="Section"
            value={sectionId}
            disabled={!classId}
            onChange={(e) => chooseSection(e.target.value)}
            className={inputClass}
          >
            <option value="">Section…</option>
            {sections.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
          {sheet && sheet.cards.length > 0 && (
            <>
              <span className="text-sm text-gray-500">
                {sheet.cards.length}{" "}
                {sheet.cards.length === 1 ? "card" : "cards"} ·{" "}
                {sheet.academic_year.name}
              </span>
              <button
                type="button"
                onClick={() => window.print()}
                className="ml-auto rounded-md bg-gray-900 px-4 py-1.5 text-sm text-white dark:bg-gray-100 dark:text-gray-900"
              >
                Print
              </button>
            </>
          )}
        </div>
      </div>

      {sheet && <IdCardSheet sheet={sheet} />}
    </div>
  );
}

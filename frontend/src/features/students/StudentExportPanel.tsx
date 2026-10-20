"use client";

import { useEffect, useState } from "react";
import { saveBlob } from "@/lib/download";
import { errorMessage } from "@/lib/errors";
import * as academicYearService from "@/services/academicYearService";
import * as classService from "@/services/classService";
import * as sectionService from "@/services/sectionService";
import * as studentToolsService from "@/services/studentToolsService";
import type { AcademicYear } from "@/types/academicYear";
import { ENROLLMENT_STATUSES } from "@/types/enrollment";
import type { SchoolClass } from "@/types/schoolClass";
import type { Section } from "@/types/section";
import type { SheetFormat } from "@/types/studentTools";

const inputClass = "input input-sm";

/**
 * Download the student register as CSV or Excel, with each student's
 * class/section/roll in the chosen year. Uses the list's search and
 * archived toggle as well.
 */
export function StudentExportPanel({
  search,
  archived,
}: {
  search: string;
  archived: boolean;
}) {
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [sections, setSections] = useState<Section[]>([]);
  const [yearId, setYearId] = useState("");
  const [classId, setClassId] = useState("");
  const [sectionId, setSectionId] = useState("");
  const [status, setStatus] = useState("");
  const [format, setFormat] = useState<SheetFormat>("xlsx");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Options: state set only in promise callbacks.
  useEffect(() => {
    let active = true;
    Promise.all([
      academicYearService.listAcademicYears(1, 100),
      classService.listClasses(1, 100),
    ])
      .then(([yearPage, classPage]) => {
        if (!active) return;
        setYears(yearPage.data);
        setYearId(String(yearPage.data.find((y) => y.is_current)?.id ?? ""));
        setClasses(classPage.data);
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
    if (!value) return;
    sectionService
      .listSections(Number(value), 1, 100)
      .then((res) => setSections(res.data))
      .catch((err) => setError(errorMessage(err)));
  }

  async function download() {
    setBusy(true);
    setError(null);
    try {
      const blob = await studentToolsService.exportStudents({
        academicYearId: yearId ? Number(yearId) : null,
        classId: classId ? Number(classId) : null,
        sectionId: sectionId ? Number(sectionId) : null,
        status,
        archived,
        search,
        format,
      });
      const year =
        years.find((y) => String(y.id) === yearId)?.name ?? "students";
      saveBlob(blob, `students-${year}.${format}`);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="card mb-4 p-5">
      <h2 className="mb-1 text-sm font-semibold">Export</h2>
      <p className="mb-3 text-xs text-slate-500">
        {search || archived
          ? `Uses the list's ${[search && `search “${search}”`, archived && "archived view"].filter(Boolean).join(" and ")}.`
          : "Every student, with their place in the chosen year."}
      </p>
      {error && (
        <p role="alert" className="alert alert-error mb-3">
          {error}
        </p>
      )}
      <div className="flex flex-wrap items-center gap-2">
        <select
          aria-label="Academic year"
          value={yearId}
          onChange={(e) => setYearId(e.target.value)}
          className={inputClass}
        >
          {years.map((y) => (
            <option key={y.id} value={y.id}>
              {y.name}
              {y.is_current ? " (current)" : ""}
            </option>
          ))}
        </select>
        <select
          aria-label="Class"
          value={classId}
          onChange={(e) => chooseClass(e.target.value)}
          className={inputClass}
        >
          <option value="">All classes</option>
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
          onChange={(e) => setSectionId(e.target.value)}
          className={inputClass}
        >
          <option value="">All sections</option>
          {sections.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </select>
        <select
          aria-label="Enrollment status"
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className={inputClass}
        >
          <option value="">Any status</option>
          {ENROLLMENT_STATUSES.map((s) => (
            <option key={s.value} value={s.value}>
              {s.label}
            </option>
          ))}
          <option value="not_enrolled">Not enrolled</option>
        </select>
        <select
          aria-label="Format"
          value={format}
          onChange={(e) => setFormat(e.target.value as SheetFormat)}
          className={inputClass}
        >
          <option value="xlsx">Excel (.xlsx)</option>
          <option value="csv">CSV</option>
        </select>
        <button
          type="button"
          disabled={busy}
          onClick={() => void download()}
          className="btn btn-primary"
        >
          {busy ? "Preparing…" : "Download"}
        </button>
      </div>
    </section>
  );
}

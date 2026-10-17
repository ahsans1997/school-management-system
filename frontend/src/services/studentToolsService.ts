import { authedFetch } from "@/services/authService";
import type {
  ExportFilters,
  IdCardSheet,
  ImportResult,
  SheetFormat,
  TransferCertificate,
} from "@/types/studentTools";

/** One student's card for the year (default: current). 422 when not enrolled. */
export function getIdCard(studentId: number): Promise<IdCardSheet> {
  return authedFetch<{ data: IdCardSheet }>(
    `/students/${studentId}/id-card`,
  ).then((r) => r.data);
}

/** Every active student's card in a section, in roll order. */
export function getSectionIdCards(sectionId: number): Promise<IdCardSheet> {
  return authedFetch<{ data: IdCardSheet }>(
    `/sections/${sectionId}/id-cards`,
  ).then((r) => r.data);
}

/** The issued TC; 404 until one is issued. */
export function getTransferCertificate(
  enrollmentId: number,
): Promise<TransferCertificate> {
  return authedFetch<{ data: TransferCertificate }>(
    `/enrollments/${enrollmentId}/transfer-certificate`,
  ).then((r) => r.data);
}

/** Issue the TC (or return the issued one, updating the conduct remark). */
export function issueTransferCertificate(
  enrollmentId: number,
  conduct: string | null,
): Promise<TransferCertificate> {
  return authedFetch<{ data: TransferCertificate }>(
    `/enrollments/${enrollmentId}/transfer-certificate`,
    { method: "POST", body: { conduct } },
  ).then((r) => r.data);
}

/**
 * Check (dryRun) or import a CSV/Excel file. A file with bad rows comes
 * back as a 422 whose body also has `rows: [{row, errors}]`.
 */
export function importStudents(
  file: File,
  dryRun: boolean,
): Promise<ImportResult> {
  const form = new FormData();
  form.append("file", file);
  form.append("dry_run", dryRun ? "1" : "0");
  return authedFetch<{ data: ImportResult }>("/students/import", {
    method: "POST",
    body: form,
  }).then((r) => r.data);
}

export function downloadImportTemplate(format: SheetFormat): Promise<Blob> {
  return authedFetch<Blob>(`/students/import/template?format=${format}`, {
    asBlob: true,
  });
}

export function exportStudents(filters: ExportFilters): Promise<Blob> {
  const params = new URLSearchParams({ format: filters.format });
  if (filters.academicYearId)
    params.set("academic_year_id", String(filters.academicYearId));
  if (filters.sectionId) params.set("section_id", String(filters.sectionId));
  else if (filters.classId) params.set("class_id", String(filters.classId));
  if (filters.status) params.set("status", filters.status);
  if (filters.archived) params.set("archived", "1");
  if (filters.search) params.set("search", filters.search);
  return authedFetch<Blob>(`/students/export?${params}`, { asBlob: true });
}

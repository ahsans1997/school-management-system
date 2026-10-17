/** The school's details printed on ID cards and certificates. */
export interface Letterhead {
  name: string;
  logo_url: string | null;
  address: string | null;
  phone: string | null;
  email: string | null;
  eiin: string | null;
  principal_name: string | null;
}

export interface IdCard {
  student_id: number;
  name: string;
  /** The printed student ID number. */
  id_no: string;
  admission_no: string;
  photo_url: string | null;
  dob: string;
  blood_group: string | null;
  class: string;
  section: string;
  roll: number;
  /** Primary guardian's phone, else the student's own. */
  contact_phone: string | null;
}

export interface IdCardSheet {
  school: Letterhead;
  academic_year: { id: number; name: string; valid_until: string };
  /** Present on a section's sheet. */
  section?: { id: number; name: string; class: string };
  cards: IdCard[];
}

export interface TransferCertificate {
  id: number;
  serial_no: string;
  issued_on: string;
  issued_by: string | null;
  conduct: string | null;
  school: Letterhead;
  student: {
    id: number;
    name: string;
    father_name: string | null;
    mother_name: string | null;
    dob: string;
    gender: string;
    student_id: string;
    admission_no: string;
    admission_date: string;
  };
  enrollment: {
    id: number;
    academic_year: string;
    class: string;
    section: string;
    roll: number;
    left_on: string | null;
    reason: string | null;
  };
}

export type SheetFormat = "csv" | "xlsx";

/** POST /students/import: a check (dry run) or the import itself. */
export interface ImportResult {
  dry_run: boolean;
  rows: number;
  /** Rows with a class and section, enrolled for the current year. */
  enrolling: number;
  /** Headers in the file that aren't import columns. */
  ignored_columns: string[];
  students?: {
    row: number;
    id: number;
    name: string;
    student_id: string;
    admission_no: string;
    class: string | null;
    section: string | null;
    roll: number | null;
  }[];
}

/** A 422 from the import lists each bad row. */
export interface ImportRowErrors {
  row: number;
  errors: string[];
}

export interface ExportFilters {
  academicYearId?: number | null;
  classId?: number | null;
  sectionId?: number | null;
  /** An enrollment status, or "not_enrolled". */
  status?: string;
  archived?: boolean;
  search?: string;
  format: SheetFormat;
}

/** The import columns, in template order, with what each takes. */
export const IMPORT_COLUMNS: { name: string; note: string }[] = [
  { name: "Student ID", note: "optional; generated when blank" },
  { name: "Admission no", note: "optional; generated when blank" },
  { name: "Name", note: "required" },
  { name: "Gender", note: "required: Male, Female or Other" },
  { name: "Date of birth", note: "required: YYYY-MM-DD or DD/MM/YYYY" },
  { name: "Blood group", note: "optional, e.g. B+" },
  { name: "Phone", note: "optional" },
  { name: "Address", note: "optional" },
  { name: "Father's name", note: "optional" },
  { name: "Mother's name", note: "optional" },
  { name: "Admission date", note: "optional; today when blank" },
  { name: "Previous school", note: "optional" },
  { name: "Class", note: "with Section: enrolls for the current year" },
  { name: "Section", note: "with Class" },
  { name: "Roll", note: "optional; next free roll when blank" },
];

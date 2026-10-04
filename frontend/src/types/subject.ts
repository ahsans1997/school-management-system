import type { Paginated } from "@/types/api";
import type { SchoolClass } from "@/types/schoolClass";

export type SubjectType = "theory" | "practical" | "theory_practical";

export interface Subject {
  id: number;
  name: string;
  /** Optional board/school code; unique when set. */
  code: string | null;
  type: SubjectType | null;
  /** How many classes teach it (present on the list and show). */
  classes_count?: number;
  created_at?: string;
}

export interface SubjectInput {
  name: string;
  code: string | null;
  type: SubjectType | null;
}

/** A subject assigned to a class (row of class_subjects). */
export interface ClassSubject {
  id: number;
  class_id: number;
  subject_id: number;
  subject: Subject;
  created_at?: string;
}

/** GET /classes/{id}/subjects also returns the parent class. */
export interface ClassSubjectPage extends Paginated<ClassSubject> {
  class: SchoolClass;
}

/** Mirrors App\Enums\SubjectType on the backend. */
export const SUBJECT_TYPES: { value: SubjectType; label: string }[] = [
  { value: "theory", label: "Theory" },
  { value: "practical", label: "Practical" },
  { value: "theory_practical", label: "Theory + practical" },
];

import type { Paginated } from "@/types/api";
import type { SchoolClass } from "@/types/schoolClass";

export interface Section {
  id: number;
  class_id: number;
  name: string;
  /** Seats; caps enrollment (enforced when students are enrolled). */
  capacity: number;
  /** Present on GET /sections/{id}. */
  class?: SchoolClass;
  created_at?: string;
}

export interface SectionInput {
  name: string;
  capacity: number;
}

/** GET /classes/{id}/sections also returns the parent class. */
export interface SectionPage extends Paginated<Section> {
  class: SchoolClass;
}

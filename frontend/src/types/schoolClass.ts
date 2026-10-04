export type Shift = "morning" | "day" | "evening";
export type Medium = "bangla" | "english";

export interface SchoolClass {
  id: number;
  name: string;
  /** Sort + promotion key: Class 1 = 1; zero/negative for pre-primary. */
  numeric_level: number;
  shift: Shift | null;
  medium: Medium | null;
  /** Present on the class list. */
  sections_count?: number;
  created_at?: string;
}

export interface SchoolClassInput {
  name: string;
  numeric_level: number;
  shift: Shift | null;
  medium: Medium | null;
}

/** Mirrors App\Enums\Shift on the backend. */
export const SHIFTS: { value: Shift; label: string }[] = [
  { value: "morning", label: "Morning" },
  { value: "day", label: "Day" },
  { value: "evening", label: "Evening" },
];

/** Mirrors App\Enums\Medium on the backend. */
export const MEDIUMS: { value: Medium; label: string }[] = [
  { value: "bangla", label: "Bangla" },
  { value: "english", label: "English" },
];

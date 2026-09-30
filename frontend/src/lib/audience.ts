import type { Audience } from "@/types/auth";

/** Landing page per audience (the audience itself is decided server-side). */
const HOME: Record<Audience, string> = {
  admin: "/admin/dashboard",
  staff: "/admin/dashboard",
  teacher: "/teacher",
  parent: "/parent",
  student: "/student",
};

export function homePathFor(audience: Audience): string {
  return HOME[audience];
}

/**
 * Audiences allowed in each area. This is UX routing only — every API call
 * is authorized and scoped server-side regardless of which page makes it.
 */
export const AREA_AUDIENCES = {
  admin: ["admin", "staff"],
  teacher: ["teacher"],
  parent: ["parent"],
  student: ["student"],
} as const satisfies Record<string, readonly Audience[]>;

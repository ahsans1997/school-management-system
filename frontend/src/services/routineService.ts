import { authedFetch } from "@/services/authService";
import type {
  MyRoutine,
  RoutineSlot,
  SectionRoutine,
  SlotInput,
} from "@/types/routine";

/** A section's routine (current year) + the subject/teacher options for the builder. */
export function getSectionRoutine(sectionId: number): Promise<SectionRoutine> {
  return authedFetch<{ data: SectionRoutine }>(
    `/sections/${sectionId}/routine`,
  ).then((r) => r.data);
}

/**
 * Create or replace a section's whole week. A clash comes back as a 422
 * whose `errors` are keyed `slots.N` (the row) — see ApiRequestError.
 */
export function saveRoutine(
  sectionId: number,
  academicYearId: number,
  slots: SlotInput[],
): Promise<SectionRoutine> {
  return authedFetch<{ data: SectionRoutine }>("/routines", {
    method: "POST",
    body: {
      section_id: sectionId,
      academic_year_id: academicYearId,
      slots: slots.map((s) => ({ ...s })),
    },
  }).then((r) => r.data);
}

export function getTeacherRoutine(
  teacherId: number,
): Promise<{ slots: RoutineSlot[] }> {
  return authedFetch<{ data: { slots: RoutineSlot[] } }>(
    `/routines/teacher/${teacherId}`,
  ).then((r) => r.data);
}

export function getRoomRoutine(
  room: string,
): Promise<{ slots: RoutineSlot[] }> {
  return authedFetch<{ data: { slots: RoutineSlot[] } }>(
    `/routines/room?room=${encodeURIComponent(room)}`,
  ).then((r) => r.data);
}

/** The caller's own routine: teacher week, or a student's/children's sections. */
export function getMyRoutine(): Promise<MyRoutine> {
  return authedFetch<{ data: MyRoutine }>("/routines/me").then((r) => r.data);
}

import { authedFetch } from "@/services/authService";
import type { DashboardPayload } from "@/types/dashboard";

/** Role-aware dashboard: the server decides which widgets the caller sees. */
export function getDashboard(): Promise<DashboardPayload> {
  return authedFetch<{ data: DashboardPayload }>("/dashboard").then(
    (r) => r.data,
  );
}

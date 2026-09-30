import type { Audience } from "@/types/auth";

export type WidgetStatus = "ready" | "pending";

export interface DashboardWidget<T = Record<string, unknown>> {
  key: string;
  title: string;
  status: WidgetStatus;
  /** Source module label, present on pending widgets. */
  module?: string;
  data: T | null;
}

export interface DashboardPayload {
  audience: Audience;
  generated_at: string;
  widgets: DashboardWidget[];
}

export interface ProfileWidgetData {
  name: string;
  email: string;
  roles: string[];
}

export interface SchoolWidgetData {
  name: string;
  logo_url: string | null;
  eiin: string | null;
  established_year: number | null;
  principal_name: string | null;
  address: string | null;
}

export interface UserStatsWidgetData {
  total: number;
  active: number;
  inactive: number;
  by_role: { role: string; count: number }[];
}

export interface RecentUsersWidgetData {
  items: {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_active: boolean;
    created_at: string | null;
  }[];
}

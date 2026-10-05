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
  /** Name of the current academic year, if one exists. */
  academic_year: string | null;
}

export interface UserStatsWidgetData {
  total: number;
  active: number;
  inactive: number;
  by_role: { role: string; count: number }[];
}

export interface StudentStatsWidgetData {
  total: number;
  admitted_this_month: number;
  archived: number;
  by_gender: { gender: string; label: string; count: number }[];
}

export interface ChildrenWidgetData {
  /** False when this login isn't linked to a guardian record. */
  linked: boolean;
  items: {
    id: number;
    name: string;
    student_id: string;
    relationship: string;
  }[];
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

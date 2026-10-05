"use client";

import type { ReactNode } from "react";
import { AppShell, type NavItem } from "@/components/AppShell";
import { RequireAuth } from "@/components/RequireAuth";
import { AREA_AUDIENCES } from "@/lib/audience";

// Each entry is hidden unless the user holds its permission. Feature modules
// add their screens here as they land.
const NAV: readonly NavItem[] = [
  { href: "/admin/dashboard", label: "Dashboard" },
  {
    href: "/admin/academic-years",
    label: "Academic years",
    permission: "academic_year.view",
  },
  { href: "/admin/classes", label: "Classes", permission: "class.view" },
  { href: "/admin/subjects", label: "Subjects", permission: "subject.view" },
  { href: "/admin/students", label: "Students", permission: "student.view" },
  {
    href: "/admin/enrollments",
    label: "Enrollments",
    permission: "enrollment.view",
  },
  { href: "/admin/guardians", label: "Guardians", permission: "guardian.view" },
  { href: "/admin/teachers", label: "Teachers", permission: "teacher.view" },
  {
    href: "/admin/attendance",
    label: "Attendance",
    permission: "attendance.view",
  },
  { href: "/admin/users", label: "Users", permission: "user.view" },
  {
    href: "/admin/roles",
    label: "Roles & permissions",
    permission: "role.view",
  },
  { href: "/admin/settings", label: "Settings", permission: "settings.view" },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <RequireAuth audiences={AREA_AUDIENCES.admin}>
      <AppShell nav={NAV}>{children}</AppShell>
    </RequireAuth>
  );
}

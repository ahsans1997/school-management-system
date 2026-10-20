"use client";

import {
  Award,
  BookOpen,
  CalendarCheck,
  CalendarClock,
  CalendarRange,
  ClipboardList,
  ClipboardPen,
  FileText,
  GraduationCap,
  IdCard,
  LayoutDashboard,
  NotebookPen,
  ReceiptText,
  School,
  Settings,
  ShieldCheck,
  Trophy,
  UserCog,
  UserRound,
  UsersRound,
  Wallet,
} from "lucide-react";
import type { ReactNode } from "react";
import { AppShell, type NavItem } from "@/components/AppShell";
import { RequireAuth } from "@/components/RequireAuth";
import { AREA_AUDIENCES } from "@/lib/audience";

// Each entry is hidden unless the user holds its permission. Feature modules
// add their screens here as they land; `section` starts a new menu group.
const NAV: readonly NavItem[] = [
  { href: "/admin/dashboard", label: "Dashboard", icon: LayoutDashboard },

  {
    section: "Students",
    href: "/admin/students",
    label: "Students",
    permission: "student.view",
    icon: GraduationCap,
  },
  {
    href: "/admin/enrollments",
    label: "Enrollments",
    permission: "enrollment.view",
    icon: ClipboardList,
  },
  {
    href: "/admin/guardians",
    label: "Guardians",
    permission: "guardian.view",
    icon: UsersRound,
  },
  {
    href: "/admin/attendance",
    label: "Attendance",
    permission: "attendance.view",
    icon: CalendarCheck,
  },
  {
    href: "/admin/id-cards",
    label: "ID cards",
    permission: "student.view",
    icon: IdCard,
  },

  {
    section: "Academics",
    href: "/admin/teachers",
    label: "Teachers",
    permission: "teacher.view",
    icon: UserRound,
  },
  {
    href: "/admin/classes",
    label: "Classes",
    permission: "class.view",
    icon: School,
  },
  {
    href: "/admin/subjects",
    label: "Subjects",
    permission: "subject.view",
    icon: BookOpen,
  },
  {
    href: "/admin/routines",
    label: "Class routine",
    permission: "routine.view",
    icon: CalendarClock,
  },
  {
    href: "/admin/exams",
    label: "Exams",
    permission: "exam.view",
    icon: NotebookPen,
  },
  {
    href: "/admin/marks",
    label: "Marks entry",
    permission: "exam.marks_entry",
    icon: ClipboardPen,
  },
  {
    href: "/admin/results",
    label: "Results",
    permission: "result.view",
    icon: Trophy,
  },
  {
    href: "/admin/grade-scales",
    label: "Grade scales",
    permission: "exam.view",
    icon: Award,
  },
  {
    href: "/admin/academic-years",
    label: "Academic years",
    permission: "academic_year.view",
    icon: CalendarRange,
  },

  {
    section: "Finance",
    href: "/admin/fees/invoices",
    label: "Invoices",
    permission: "fee.view",
    icon: ReceiptText,
  },
  {
    href: "/admin/fees/dues",
    label: "Due report",
    permission: "fee.report",
    icon: FileText,
  },
  {
    href: "/admin/fees",
    label: "Fee setup",
    permission: "fee.view",
    icon: Wallet,
    exact: true,
  },

  {
    section: "System",
    href: "/admin/users",
    label: "Users",
    permission: "user.view",
    icon: UserCog,
  },
  {
    href: "/admin/roles",
    label: "Roles & permissions",
    permission: "role.view",
    icon: ShieldCheck,
  },
  {
    href: "/admin/settings",
    label: "Settings",
    permission: "settings.view",
    icon: Settings,
  },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <RequireAuth audiences={AREA_AUDIENCES.admin}>
      <AppShell nav={NAV}>{children}</AppShell>
    </RequireAuth>
  );
}

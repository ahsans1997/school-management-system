"use client";

import {
  CalendarCheck,
  CalendarClock,
  ClipboardPen,
  House,
  NotebookPen,
} from "lucide-react";
import type { ReactNode } from "react";
import type { NavItem } from "@/components/AppShell";
import { PortalLayout } from "@/components/PortalLayout";
import { AREA_AUDIENCES } from "@/lib/audience";

const NAV: readonly NavItem[] = [
  { href: "/teacher", label: "Home", exact: true, icon: House },
  {
    href: "/teacher/attendance",
    label: "Attendance",
    permission: "attendance.view",
    icon: CalendarCheck,
  },
  { href: "/teacher/routine", label: "My routine", icon: CalendarClock },
  { href: "/teacher/exams", label: "Exams", icon: NotebookPen },
  {
    href: "/teacher/marks",
    label: "Marks entry",
    permission: "exam.marks_entry",
    icon: ClipboardPen,
  },
];

export default function TeacherLayout({ children }: { children: ReactNode }) {
  return (
    <PortalLayout audiences={AREA_AUDIENCES.teacher} nav={NAV}>
      {children}
    </PortalLayout>
  );
}

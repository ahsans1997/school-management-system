"use client";

import {
  CalendarClock,
  House,
  NotebookPen,
  Trophy,
  Wallet,
} from "lucide-react";
import type { ReactNode } from "react";
import type { NavItem } from "@/components/AppShell";
import { PortalLayout } from "@/components/PortalLayout";
import { AREA_AUDIENCES } from "@/lib/audience";

const NAV: readonly NavItem[] = [
  { href: "/student", label: "Home", exact: true, icon: House },
  { href: "/student/routine", label: "My routine", icon: CalendarClock },
  { href: "/student/exams", label: "Exams", icon: NotebookPen },
  { href: "/student/results", label: "Results", icon: Trophy },
  { href: "/student/fees", label: "Fees", icon: Wallet },
];

export default function StudentLayout({ children }: { children: ReactNode }) {
  return (
    <PortalLayout audiences={AREA_AUDIENCES.student} nav={NAV}>
      {children}
    </PortalLayout>
  );
}

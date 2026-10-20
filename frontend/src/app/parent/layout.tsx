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
  { href: "/parent", label: "Home", exact: true, icon: House },
  { href: "/parent/routine", label: "Class routines", icon: CalendarClock },
  { href: "/parent/exams", label: "Exams", icon: NotebookPen },
  { href: "/parent/results", label: "Results", icon: Trophy },
  { href: "/parent/fees", label: "Fees", icon: Wallet },
];

export default function ParentLayout({ children }: { children: ReactNode }) {
  return (
    <PortalLayout audiences={AREA_AUDIENCES.parent} nav={NAV}>
      {children}
    </PortalLayout>
  );
}

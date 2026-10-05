import type { ReactNode } from "react";
import type { NavItem } from "@/components/AppShell";
import { PortalLayout } from "@/components/PortalLayout";
import { AREA_AUDIENCES } from "@/lib/audience";

const NAV: readonly NavItem[] = [
  { href: "/teacher", label: "Home", exact: true },
  {
    href: "/teacher/attendance",
    label: "Attendance",
    permission: "attendance.view",
  },
  { href: "/teacher/routine", label: "My routine" },
];

export default function TeacherLayout({ children }: { children: ReactNode }) {
  return (
    <PortalLayout audiences={AREA_AUDIENCES.teacher} nav={NAV}>
      {children}
    </PortalLayout>
  );
}

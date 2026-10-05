import type { ReactNode } from "react";
import type { NavItem } from "@/components/AppShell";
import { PortalLayout } from "@/components/PortalLayout";
import { AREA_AUDIENCES } from "@/lib/audience";

const NAV: readonly NavItem[] = [
  { href: "/parent", label: "Home", exact: true },
  { href: "/parent/routine", label: "Class routines" },
  { href: "/parent/exams", label: "Exams" },
];

export default function ParentLayout({ children }: { children: ReactNode }) {
  return (
    <PortalLayout audiences={AREA_AUDIENCES.parent} nav={NAV}>
      {children}
    </PortalLayout>
  );
}

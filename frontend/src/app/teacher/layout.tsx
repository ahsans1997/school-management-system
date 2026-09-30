import type { ReactNode } from "react";
import { PortalLayout } from "@/components/PortalLayout";
import { AREA_AUDIENCES } from "@/lib/audience";

export default function TeacherLayout({ children }: { children: ReactNode }) {
  return (
    <PortalLayout audiences={AREA_AUDIENCES.teacher}>{children}</PortalLayout>
  );
}

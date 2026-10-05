"use client";

import type { ReactNode } from "react";
import { AppShell, type NavItem } from "@/components/AppShell";
import { RequireAuth } from "@/components/RequireAuth";
import type { Audience } from "@/types/auth";

/** Chrome for the teacher / parent / student portals (no admin navigation). */
export function PortalLayout({
  audiences,
  nav,
  children,
}: {
  audiences: readonly Audience[];
  /** Optional portal navigation (permission-filtered like the admin nav). */
  nav?: readonly NavItem[];
  children: ReactNode;
}) {
  return (
    <RequireAuth audiences={audiences}>
      <AppShell nav={nav}>{children}</AppShell>
    </RequireAuth>
  );
}

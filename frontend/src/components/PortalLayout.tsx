"use client";

import type { ReactNode } from "react";
import { AppShell } from "@/components/AppShell";
import { RequireAuth } from "@/components/RequireAuth";
import type { Audience } from "@/types/auth";

/** Chrome for the teacher / parent / student portals (no admin navigation). */
export function PortalLayout({
  audiences,
  children,
}: {
  audiences: readonly Audience[];
  children: ReactNode;
}) {
  return (
    <RequireAuth audiences={audiences}>
      <AppShell>{children}</AppShell>
    </RequireAuth>
  );
}

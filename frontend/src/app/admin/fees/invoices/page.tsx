"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { InvoicesPage } from "@/features/fees/InvoicesPage";

export default function InvoicesRoute() {
  return (
    <RequireAuth permission="fee.view">
      <InvoicesPage />
    </RequireAuth>
  );
}

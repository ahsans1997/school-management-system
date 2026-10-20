"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { FeeSetupPage } from "@/features/fees/FeeSetupPage";

export default function FeeSetupRoute() {
  return (
    <RequireAuth permission="fee.view">
      <FeeSetupPage />
    </RequireAuth>
  );
}

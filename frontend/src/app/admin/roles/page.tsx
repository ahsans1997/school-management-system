"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { RolesManager } from "@/features/roles/RolesManager";

export default function RolesPage() {
  return (
    <RequireAuth permission="role.view">
      <RolesManager />
    </RequireAuth>
  );
}

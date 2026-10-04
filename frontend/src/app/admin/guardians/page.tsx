"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { GuardiansManager } from "@/features/guardians/GuardiansManager";

export default function GuardiansPage() {
  return (
    <RequireAuth permission="guardian.view">
      <GuardiansManager />
    </RequireAuth>
  );
}

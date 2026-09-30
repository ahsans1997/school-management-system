"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { SettingsForm } from "@/features/settings/SettingsForm";

export default function SettingsPage() {
  return (
    <RequireAuth permission="settings.view">
      <SettingsForm />
    </RequireAuth>
  );
}

"use client";

import { RequireAuth } from "@/components/RequireAuth";
import { UsersManager } from "@/features/users/UsersManager";

export default function UsersPage() {
  return (
    <RequireAuth permission="user.view">
      <UsersManager />
    </RequireAuth>
  );
}

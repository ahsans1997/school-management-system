"use client";

import { useEffect, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/features/auth/AuthContext";
import { homePathFor } from "@/lib/audience";
import type { Audience } from "@/types/auth";

interface RequireAuthProps {
  children: ReactNode;
  /** Optional permission the user must hold to view the content. */
  permission?: string;
  /** Optional audiences allowed in this area; others go to their own home. */
  audiences?: readonly Audience[];
}

/**
 * Client-side route guard. Redirects unauthenticated visitors to /login,
 * sends users outside `audiences` to their own area, and blocks users who
 * lack `permission`. UX only — the API enforces access on every call.
 */
export function RequireAuth({
  children,
  permission,
  audiences,
}: RequireAuthProps) {
  const { user, loading, can } = useAuth();
  const router = useRouter();
  const wrongArea = Boolean(
    user && audiences && !audiences.includes(user.audience),
  );

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/login");
    } else if (wrongArea) {
      router.replace(homePathFor(user.audience));
    }
  }, [loading, user, wrongArea, router]);

  if (loading) {
    return <div className="p-8 text-sm text-gray-500">Loading…</div>;
  }

  if (!user || wrongArea) {
    return null;
  }

  if (permission && !can(permission)) {
    return (
      <div className="p-8 text-sm text-red-600">
        You don’t have permission to view this page.
      </div>
    );
  }

  return <>{children}</>;
}

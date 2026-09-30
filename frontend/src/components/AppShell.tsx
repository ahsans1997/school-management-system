"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import type { ReactNode } from "react";
import { useAuth } from "@/features/auth/AuthContext";

export interface NavItem {
  href: string;
  label: string;
  /** Hidden unless the user holds this permission. */
  permission?: string;
}

interface AppShellProps {
  children: ReactNode;
  nav?: readonly NavItem[];
}

/**
 * Signed-in chrome shared by every area: header (user + logout) and, when
 * given, a navigation list filtered by the user's permissions.
 */
export function AppShell({ children, nav = [] }: AppShellProps) {
  const { user, can, logout } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const items = nav.filter((item) => !item.permission || can(item.permission));

  async function handleLogout() {
    await logout();
    router.replace("/login");
  }

  return (
    <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-950">
      <header className="flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <span className="font-semibold">School Management</span>
        <div className="flex min-w-0 items-center gap-3 text-sm">
          <span className="truncate text-gray-700 dark:text-gray-300">
            {user?.name}
          </span>
          {user && (
            <span className="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 capitalize dark:bg-gray-800 dark:text-gray-400">
              {user.audience}
            </span>
          )}
          <button
            type="button"
            onClick={handleLogout}
            className="rounded-md border border-gray-300 px-2.5 py-1 text-xs hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
          >
            Log out
          </button>
        </div>
      </header>

      <div className="flex flex-1 flex-col md:flex-row">
        {items.length > 0 && (
          <nav
            aria-label="Main"
            className="flex gap-1 overflow-x-auto border-b border-gray-200 bg-white px-2 py-2 md:w-56 md:shrink-0 md:flex-col md:border-r md:border-b-0 md:py-4 dark:border-gray-800 dark:bg-gray-900"
          >
            {items.map((item) => {
              const active =
                pathname === item.href || pathname.startsWith(`${item.href}/`);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  aria-current={active ? "page" : undefined}
                  className={`rounded-md px-3 py-1.5 text-sm whitespace-nowrap ${
                    active
                      ? "bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900"
                      : "text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                  }`}
                >
                  {item.label}
                </Link>
              );
            })}
          </nav>
        )}
        <main className="min-w-0 flex-1">{children}</main>
      </div>
    </div>
  );
}

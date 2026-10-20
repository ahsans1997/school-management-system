"use client";

import { GraduationCap, LogOut, Menu, X, type LucideIcon } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useState, type ReactNode } from "react";
import { ThemeToggle } from "@/components/ThemeToggle";
import { useAuth } from "@/features/auth/AuthContext";

export interface NavItem {
  href: string;
  label: string;
  /** Hidden unless the user holds this permission. */
  permission?: string;
  /** Active only on this exact path (for a portal's home link). */
  exact?: boolean;
  icon?: LucideIcon;
  /** Group heading; items without one stay in the group above them. */
  section?: string;
}

interface AppShellProps {
  children: ReactNode;
  nav?: readonly NavItem[];
}

const AREA_LABEL: Record<string, string> = {
  admin: "Administration",
  staff: "Staff",
  teacher: "Teacher portal",
  parent: "Parent portal",
  student: "Student portal",
};

/** "Farhana Akter" → "FA". */
function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

/** Consecutive items under the same heading. */
function groupsOf(items: readonly NavItem[]) {
  const groups: { section?: string; items: NavItem[] }[] = [];
  for (const item of items) {
    const last = groups[groups.length - 1];
    if (!last || (item.section && item.section !== last.section)) {
      groups.push({ section: item.section, items: [item] });
    } else {
      last.items.push(item);
    }
  }
  return groups;
}

function Brand() {
  return (
    <div className="flex items-center gap-3 px-5 py-5">
      <div className="from-brand-500 shadow-brand-600/30 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-linear-to-br to-indigo-600 text-white shadow-lg">
        <GraduationCap className="h-5 w-5" />
      </div>
      <div className="min-w-0 leading-tight">
        <p className="truncate text-[15px] font-bold tracking-tight text-slate-900 dark:text-white">
          School Management
        </p>
        <p className="text-xs text-slate-500 dark:text-slate-400">System</p>
      </div>
    </div>
  );
}

function NavList({
  items,
  pathname,
  onNavigate,
}: {
  items: readonly NavItem[];
  pathname: string;
  onNavigate?: () => void;
}) {
  return (
    <nav
      aria-label="Main"
      className="flex-1 space-y-5 overflow-y-auto px-3 pb-6"
    >
      {groupsOf(items).map((group, i) => (
        <div key={group.section ?? `group-${i}`}>
          {group.section && (
            <p className="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-slate-400 uppercase dark:text-slate-500">
              {group.section}
            </p>
          )}
          <ul className="space-y-0.5">
            {group.items.map((item) => {
              const active =
                pathname === item.href ||
                (!item.exact && pathname.startsWith(`${item.href}/`));
              const Icon = item.icon;
              return (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    onClick={onNavigate}
                    aria-current={active ? "page" : undefined}
                    className={`group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                      active
                        ? "bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                        : "text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    }`}
                  >
                    {Icon && (
                      <Icon
                        className={`h-[18px] w-[18px] shrink-0 ${
                          active
                            ? "text-brand-600 dark:text-brand-400"
                            : "text-slate-400 group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300"
                        }`}
                      />
                    )}
                    <span className="truncate">{item.label}</span>
                    {active && (
                      <span className="bg-brand-500 ml-auto h-1.5 w-1.5 rounded-full" />
                    )}
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      ))}
    </nav>
  );
}

/**
 * Signed-in chrome shared by every area: a sidebar with the permission-
 * filtered navigation (a slide-in drawer on small screens) and a top bar
 * with the theme switch and the signed-in user. Hidden when printing.
 */
export function AppShell({ children, nav = [] }: AppShellProps) {
  const { user, can, logout } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const items = nav.filter((item) => !item.permission || can(item.permission));

  async function handleLogout() {
    await logout();
    router.replace("/login");
  }

  return (
    <div className="flex min-h-screen bg-[var(--background)] print:block print:bg-white">
      {items.length > 0 && (
        <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-slate-200/80 bg-white lg:flex dark:border-slate-800 dark:bg-slate-900 print:hidden">
          <Brand />
          <NavList items={items} pathname={pathname} />
        </aside>
      )}

      {/* Small screens: the same navigation in a drawer. */}
      {drawerOpen && (
        <div className="fixed inset-0 z-40 lg:hidden print:hidden">
          <button
            type="button"
            aria-label="Close menu"
            onClick={() => setDrawerOpen(false)}
            className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
          />
          <aside className="relative flex h-full w-72 max-w-[85%] flex-col bg-white shadow-2xl dark:bg-slate-900">
            <div className="flex items-center justify-between pr-3">
              <Brand />
              <button
                type="button"
                aria-label="Close menu"
                onClick={() => setDrawerOpen(false)}
                className="btn btn-ghost h-9 w-9 p-0"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <NavList
              items={items}
              pathname={pathname}
              onNavigate={() => setDrawerOpen(false)}
            />
          </aside>
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur-md sm:px-6 dark:border-slate-800 dark:bg-slate-900/80 print:hidden">
          {items.length > 0 && (
            <button
              type="button"
              aria-label="Open menu"
              onClick={() => setDrawerOpen(true)}
              className="btn btn-ghost h-9 w-9 p-0 lg:hidden"
            >
              <Menu className="h-5 w-5" />
            </button>
          )}
          <p className="truncate text-sm font-semibold text-slate-700 dark:text-slate-200">
            {user ? (AREA_LABEL[user.audience] ?? "") : ""}
          </p>

          <div className="ml-auto flex items-center gap-2">
            <ThemeToggle />
            {user && (
              <div className="flex items-center gap-2.5 rounded-full border border-slate-200 bg-white py-1 pr-3 pl-1 dark:border-slate-700 dark:bg-slate-800">
                <span
                  aria-hidden
                  className="from-brand-500 flex h-8 w-8 items-center justify-center rounded-full bg-linear-to-br to-indigo-600 text-xs font-bold text-white"
                >
                  {initials(user.name)}
                </span>
                <span className="hidden min-w-0 leading-tight sm:block">
                  <span className="block max-w-40 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {user.name}
                  </span>
                  <span className="block text-xs text-slate-500 capitalize dark:text-slate-400">
                    {user.roles[0] ?? user.audience}
                  </span>
                </span>
              </div>
            )}
            <button
              type="button"
              onClick={handleLogout}
              aria-label="Log out"
              title="Log out"
              className="btn btn-ghost h-9 w-9 rounded-full p-0 hover:text-red-600 dark:hover:text-red-400"
            >
              <LogOut className="h-[18px] w-[18px]" />
            </button>
          </div>
        </header>

        <main className="min-w-0 flex-1">{children}</main>
      </div>
    </div>
  );
}

"use client";

import { useEffect, useState, type ReactNode } from "react";
import { formatCount, formatDate } from "@/lib/format";
import { ApiRequestError } from "@/services/apiClient";
import { getDashboard } from "@/services/dashboardService";
import type {
  DashboardPayload,
  DashboardWidget,
  ProfileWidgetData,
  AssignedSubjectsWidgetData,
  ChildrenWidgetData,
  RecentUsersWidgetData,
  SchoolWidgetData,
  StudentStatsWidgetData,
  TeacherStatsWidgetData,
  UserStatsWidgetData,
} from "@/types/dashboard";

/** Widget keys with a dedicated renderer below; anything else uses the fallback. */
const CUSTOM_KEYS = new Set([
  "profile",
  "school",
  "user_stats",
  "recent_users",
  "student_stats",
  "children",
  "teacher_stats",
  "assigned_subjects",
]);

function Card({
  title,
  children,
  wide = false,
}: {
  title: string;
  children: ReactNode;
  wide?: boolean;
}) {
  return (
    <section
      className={`min-w-0 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 ${
        wide ? "md:col-span-2" : ""
      }`}
    >
      <h2 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
        {title}
      </h2>
      {children}
    </section>
  );
}

/** Stat tile: sentence-case label + semibold, auto-compacted value. */
function StatTile({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <div className="text-sm text-gray-500 dark:text-gray-400">{label}</div>
      <div className="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">
        {formatCount(value)}
      </div>
    </div>
  );
}

function Details({ rows }: { rows: [string, ReactNode][] }) {
  const shown = rows.filter(([, value]) => value !== null && value !== "");
  if (shown.length === 0) {
    return <p className="text-sm text-gray-500">No details yet.</p>;
  }
  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
      {shown.map(([label, value]) => (
        <div key={label} className="contents">
          <dt className="text-gray-500 dark:text-gray-400">{label}</dt>
          <dd className="min-w-0 break-words text-gray-900 dark:text-gray-100">
            {value}
          </dd>
        </div>
      ))}
    </dl>
  );
}

function SchoolCard({ data }: { data: SchoolWidgetData }) {
  return (
    <Card title="School">
      <div className="mb-3 flex items-center gap-3">
        {data.logo_url && (
          // Dynamic asset served from the API host.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={data.logo_url}
            alt=""
            className="h-12 w-auto max-w-24 object-contain"
          />
        )}
        <span className="text-lg font-semibold">{data.name}</span>
      </div>
      <Details
        rows={[
          ["Current session", data.academic_year],
          ["EIIN", data.eiin],
          ["Established", data.established_year],
          ["Principal", data.principal_name],
          ["Address", data.address],
        ]}
      />
    </Card>
  );
}

function ProfileCard({ data }: { data: ProfileWidgetData }) {
  return (
    <Card title="My account">
      <Details
        rows={[
          ["Name", data.name],
          ["Email", data.email],
          ["Roles", data.roles.length ? data.roles.join(", ") : "None"],
        ]}
      />
    </Card>
  );
}

function UsersByRoleCard({ data }: { data: UserStatsWidgetData }) {
  const rows = [...data.by_role].sort(
    (a, b) => b.count - a.count || a.role.localeCompare(b.role),
  );
  return (
    <Card title="Users by role">
      <table className="w-full text-sm">
        <thead>
          <tr className="text-left text-gray-500 dark:text-gray-400">
            <th className="pb-2 font-medium">Role</th>
            <th className="pb-2 text-right font-medium">Users</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr
              key={row.role}
              className="border-t border-gray-100 dark:border-gray-800"
            >
              <td className="py-1.5">{row.role}</td>
              <td className="py-1.5 text-right tabular-nums">{row.count}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}

function RecentUsersCard({ data }: { data: RecentUsersWidgetData }) {
  return (
    <Card title="Recently added users" wide>
      {data.items.length === 0 ? (
        <p className="text-sm text-gray-500">No users yet.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-gray-500 dark:text-gray-400">
                <th className="pb-2 font-medium">Name</th>
                <th className="pb-2 font-medium">Email</th>
                <th className="pb-2 font-medium">Role</th>
                <th className="pb-2 font-medium">Status</th>
                <th className="pb-2 font-medium">Added</th>
              </tr>
            </thead>
            <tbody>
              {data.items.map((u) => (
                <tr
                  key={u.id}
                  className="border-t border-gray-100 dark:border-gray-800"
                >
                  <td className="py-1.5 pr-3">{u.name}</td>
                  <td className="py-1.5 pr-3">{u.email}</td>
                  <td className="py-1.5 pr-3">{u.roles.join(", ") || "—"}</td>
                  <td className="py-1.5 pr-3">
                    {u.is_active ? "Active" : "Disabled"}
                  </td>
                  <td className="py-1.5 whitespace-nowrap">
                    {formatDate(u.created_at)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

function StudentsByGenderCard({ data }: { data: StudentStatsWidgetData }) {
  return (
    <Card title="Students by gender">
      <table className="w-full text-sm">
        <tbody>
          {data.by_gender.map((row) => (
            <tr
              key={row.gender}
              className="border-t border-gray-100 first:border-t-0 dark:border-gray-800"
            >
              <td className="py-1.5">{row.label}</td>
              <td className="py-1.5 text-right tabular-nums">{row.count}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}

/** A parent's own children (the API scopes this to their guardian record). */
function ChildrenCard({ data }: { data: ChildrenWidgetData }) {
  return (
    <Card title="My children" wide>
      {!data.linked ? (
        <p className="text-sm text-gray-500">
          Your login isn&apos;t linked to a guardian record yet. Please contact
          the school office.
        </p>
      ) : data.items.length === 0 ? (
        <p className="text-sm text-gray-500">
          No children are linked to you yet.
        </p>
      ) : (
        <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
          {data.items.map((child) => (
            <li key={child.id} className="flex justify-between gap-3 py-2">
              <span className="font-medium">{child.name}</span>
              <span className="text-gray-500 tabular-nums dark:text-gray-400">
                ID {child.student_id}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

/** A teacher's own subjects and class-teacher sections (scoped by the API). */
function AssignedSubjectsCard({ data }: { data: AssignedSubjectsWidgetData }) {
  return (
    <Card title="My subjects" wide>
      {!data.linked ? (
        <p className="text-sm text-gray-500">
          Your login isn&apos;t linked to a teacher record yet. Please contact
          the school office.
        </p>
      ) : data.subjects.length === 0 && data.class_teacher_of.length === 0 ? (
        <p className="text-sm text-gray-500">Nothing is assigned to you yet.</p>
      ) : (
        <div className="space-y-3 text-sm">
          {data.class_teacher_of.length > 0 && (
            <p>
              Class teacher of{" "}
              <span className="font-medium">
                {data.class_teacher_of
                  .map((c) => `${c.class} ${c.section}`)
                  .join(", ")}
              </span>
            </p>
          )}
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {data.subjects.map((s) => (
              <li
                key={`${s.class}-${s.section}-${s.subject}`}
                className="flex justify-between gap-3 py-2"
              >
                <span className="font-medium">{s.subject}</span>
                <span className="text-gray-500 dark:text-gray-400">
                  {s.class} {s.section}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </Card>
  );
}

/** Safety net for a live widget that doesn't have a renderer yet. */
function GenericCard({ widget }: { widget: DashboardWidget }) {
  const rows = Object.entries(widget.data ?? {}).map(
    ([key, value]): [string, ReactNode] => [
      key,
      typeof value === "object" ? JSON.stringify(value) : String(value),
    ],
  );
  return (
    <Card title={widget.title}>
      <Details rows={rows} />
    </Card>
  );
}

function messageOf(error: unknown): string {
  return error instanceof ApiRequestError
    ? error.error.message
    : "Couldn’t load the dashboard.";
}

export function DashboardView() {
  const [payload, setPayload] = useState<DashboardPayload | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getDashboard()
      .then(setPayload)
      .catch((err) => setError(messageOf(err)));
  }, []);

  if (error) {
    return (
      <p role="alert" className="p-6 text-sm text-red-600">
        {error}
      </p>
    );
  }
  if (!payload) {
    return <p className="p-6 text-sm text-gray-500">Loading…</p>;
  }

  const ready = payload.widgets.filter((w) => w.status === "ready");
  const pending = payload.widgets.filter((w) => w.status === "pending");
  const find = <T,>(key: string) =>
    ready.find((w) => w.key === key)?.data as T | undefined;

  const profile = find<ProfileWidgetData>("profile");
  const school = find<SchoolWidgetData>("school");
  const stats = find<UserStatsWidgetData>("user_stats");
  const recent = find<RecentUsersWidgetData>("recent_users");
  const students = find<StudentStatsWidgetData>("student_stats");
  const children = find<ChildrenWidgetData>("children");
  const teachers = find<TeacherStatsWidgetData>("teacher_stats");
  const assigned = find<AssignedSubjectsWidgetData>("assigned_subjects");
  const others = ready.filter((w) => !CUSTOM_KEYS.has(w.key));

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-semibold">
          Welcome{profile ? `, ${profile.name}` : ""}
        </h1>
        {school && (
          <p className="text-sm text-gray-500 dark:text-gray-400">
            {school.name}
          </p>
        )}
      </div>

      {stats && (
        <div className="grid gap-4 sm:grid-cols-3">
          <StatTile label="Total users" value={stats.total} />
          <StatTile label="Active" value={stats.active} />
          <StatTile label="Disabled" value={stats.inactive} />
        </div>
      )}

      {students && (
        <div className="grid gap-4 sm:grid-cols-3">
          <StatTile label="Students" value={students.total} />
          <StatTile
            label="Admitted this month"
            value={students.admitted_this_month}
          />
          <StatTile label="Archived" value={students.archived} />
        </div>
      )}

      {teachers && (
        <div className="grid gap-4 sm:grid-cols-3">
          <StatTile label="Teachers" value={teachers.total} />
          <StatTile label="With a login" value={teachers.with_login} />
          <StatTile label="Not yet assigned" value={teachers.unassigned} />
        </div>
      )}

      <div className="grid gap-4 md:grid-cols-2">
        {assigned && <AssignedSubjectsCard data={assigned} />}
        {children && <ChildrenCard data={children} />}
        {school && <SchoolCard data={school} />}
        {profile && <ProfileCard data={profile} />}
        {stats && <UsersByRoleCard data={stats} />}
        {students && <StudentsByGenderCard data={students} />}
        {recent && <RecentUsersCard data={recent} />}
        {others.map((w) => (
          <GenericCard key={w.key} widget={w} />
        ))}
      </div>

      {pending.length > 0 && (
        <section>
          <h2 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
            Coming as modules are built
          </h2>
          <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {pending.map((w) => (
              <li
                key={w.key}
                className="rounded-md border border-dashed border-gray-300 p-3 text-sm dark:border-gray-700"
              >
                <span className="block font-medium text-gray-700 dark:text-gray-300">
                  {w.title}
                </span>
                <span className="text-xs text-gray-500 dark:text-gray-400">
                  Module {w.module}
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

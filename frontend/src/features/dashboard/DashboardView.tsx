"use client";

import {
  Building,
  CalendarCheck,
  CalendarClock,
  ClipboardCheck,
  ClipboardPen,
  GraduationCap,
  Hourglass,
  NotebookPen,
  ChartPie,
  ShieldCheck,
  Sparkles,
  Trophy,
  UserPlus,
  UserRound,
  Users,
  UserX,
  Wallet,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { useEffect, useState, type ReactNode } from "react";
import {
  formatCount,
  formatDate,
  formatDay,
  formatMoney,
  formatPeriod,
} from "@/lib/format";
import { ApiRequestError } from "@/services/apiClient";
import { getDashboard } from "@/services/dashboardService";
import type {
  DashboardPayload,
  DashboardWidget,
  ProfileWidgetData,
  AssignedSubjectsWidgetData,
  AttendanceTasksWidgetData,
  AttendanceTodayWidgetData,
  ChildAttendanceWidgetData,
  MyAttendanceWidgetData,
  PendingMarksWidgetData,
  PublishedResultsWidgetData,
  ChildrenWidgetData,
  FeeSummaryWidgetData,
  MyFeesWidgetData,
  RecentUsersWidgetData,
  SchoolWidgetData,
  StudentStatsWidgetData,
  TeacherStatsWidgetData,
  TodaysPeriodsWidgetData,
  UpcomingExamsWidgetData,
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
  "attendance_today",
  "attendance_tasks",
  "child_attendance",
  "my_attendance",
  "todays_classes",
  "my_routine",
  "upcoming_exams",
  "pending_marks",
  "my_results",
  "child_results",
  "pending_fees",
  "my_fees",
  "child_fees",
]);

type Tone =
  | "blue"
  | "violet"
  | "emerald"
  | "orange"
  | "rose"
  | "cyan"
  | "amber"
  | "indigo";

/** Solid gradient tiles (full class strings, so Tailwind sees them). */
const TILE: Record<Tone, string> = {
  blue: "from-blue-500 to-blue-700 shadow-blue-500/30",
  violet: "from-violet-500 to-purple-700 shadow-violet-500/30",
  emerald: "from-emerald-500 to-teal-600 shadow-emerald-500/30",
  orange: "from-orange-400 to-orange-600 shadow-orange-500/30",
  rose: "from-rose-500 to-pink-600 shadow-rose-500/30",
  cyan: "from-cyan-500 to-sky-600 shadow-cyan-500/30",
  amber: "from-amber-400 to-orange-500 shadow-amber-500/30",
  indigo: "from-indigo-500 to-indigo-700 shadow-indigo-500/30",
};

/** Soft tints for card header icons. */
const SOFT: Record<Tone, string> = {
  blue: "bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300",
  violet:
    "bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300",
  emerald:
    "bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300",
  orange:
    "bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-300",
  rose: "bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300",
  cyan: "bg-cyan-50 text-cyan-600 dark:bg-cyan-500/15 dark:text-cyan-300",
  amber: "bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300",
  indigo:
    "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300",
};

function Card({
  title,
  icon: Icon,
  tone = "blue",
  action,
  children,
  wide = false,
}: {
  title: string;
  icon?: LucideIcon;
  tone?: Tone;
  /** Small link shown at the right of the header. */
  action?: ReactNode;
  children: ReactNode;
  wide?: boolean;
}) {
  return (
    <section className={`card min-w-0 p-5 ${wide ? "md:col-span-2" : ""}`}>
      <header className="mb-4 flex items-center gap-3">
        {Icon && (
          <span
            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${SOFT[tone]}`}
          >
            <Icon className="h-[18px] w-[18px]" />
          </span>
        )}
        <h2 className="text-[15px] font-semibold text-slate-800 dark:text-slate-100">
          {title}
        </h2>
        {action && <div className="ml-auto text-xs">{action}</div>}
      </header>
      {children}
    </section>
  );
}

/** Colourful stat tile: label, big number, icon, optional hint. */
function StatTile({
  label,
  value,
  icon: Icon,
  tone,
  hint,
}: {
  label: string;
  value: number | string;
  icon: LucideIcon;
  tone: Tone;
  hint?: string;
}) {
  return (
    <div
      className={`relative overflow-hidden rounded-2xl bg-linear-to-br p-5 text-white shadow-lg ${TILE[tone]}`}
    >
      <span
        aria-hidden
        className="absolute -top-8 -right-8 h-28 w-28 rounded-full bg-white/10"
      />
      <span
        aria-hidden
        className="absolute -right-3 -bottom-10 h-24 w-24 rounded-full bg-white/10"
      />
      <div className="relative flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-white/85">{label}</p>
          <p className="mt-2 text-3xl font-bold tracking-tight tabular-nums">
            {typeof value === "number" ? formatCount(value) : value}
          </p>
          {hint && (
            <p className="mt-1 truncate text-xs text-white/80">{hint}</p>
          )}
        </div>
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 ring-1 ring-white/25">
          <Icon className="h-5 w-5" />
        </span>
      </div>
    </div>
  );
}

function Details({ rows }: { rows: [string, ReactNode][] }) {
  const shown = rows.filter(([, value]) => value !== null && value !== "");
  if (shown.length === 0) {
    return <p className="text-sm text-slate-500">No details yet.</p>;
  }
  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-5 gap-y-2 text-sm">
      {shown.map(([label, value]) => (
        <div key={label} className="contents">
          <dt className="text-slate-500 dark:text-slate-400">{label}</dt>
          <dd className="min-w-0 font-medium break-words text-slate-800 dark:text-slate-100">
            {value}
          </dd>
        </div>
      ))}
    </dl>
  );
}

const muted = "text-sm text-slate-500 dark:text-slate-400";
const list = "divide-y divide-slate-100 text-sm dark:divide-slate-800";

function SchoolCard({ data }: { data: SchoolWidgetData }) {
  return (
    <Card title="School" icon={Building} tone="indigo">
      <div className="mb-4 flex items-center gap-3">
        {data.logo_url && (
          // Dynamic asset served from the API host.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={data.logo_url}
            alt=""
            onError={(e) => {
              e.currentTarget.style.display = "none";
            }}
            className="h-12 w-auto max-w-24 object-contain"
          />
        )}
        <span className="text-lg font-bold text-slate-900 dark:text-white">
          {data.name}
        </span>
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
    <Card title="My account" icon={UserRound} tone="violet">
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

/** Users per role as horizontal bars. */
function UsersByRoleCard({ data }: { data: UserStatsWidgetData }) {
  const rows = [...data.by_role]
    .filter((r) => r.count > 0)
    .sort((a, b) => b.count - a.count || a.role.localeCompare(b.role));
  const max = Math.max(1, ...rows.map((r) => r.count));
  return (
    <Card title="Users by role" icon={ShieldCheck} tone="cyan">
      {rows.length === 0 ? (
        <p className={muted}>No users yet.</p>
      ) : (
        <ul className="space-y-3">
          {rows.map((row) => (
            <li key={row.role}>
              <div className="mb-1 flex justify-between text-sm">
                <span className="font-medium text-slate-700 dark:text-slate-200">
                  {row.role}
                </span>
                <span className="text-slate-500 tabular-nums">{row.count}</span>
              </div>
              <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                  className="to-brand-600 h-full rounded-full bg-linear-to-r from-cyan-400"
                  style={{ width: `${(row.count / max) * 100}%` }}
                />
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join("");
}

function RecentUsersCard({ data }: { data: RecentUsersWidgetData }) {
  return (
    <Card
      title="Recently added users"
      icon={UserPlus}
      tone="blue"
      wide
      action={
        <Link href="/admin/users" className="link">
          All users →
        </Link>
      }
    >
      {data.items.length === 0 ? (
        <p className={muted}>No users yet.</p>
      ) : (
        <ul className={list}>
          {data.items.map((u) => (
            <li key={u.id} className="flex items-center gap-3 py-2.5">
              <span className="from-brand-500 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-linear-to-br to-indigo-600 text-xs font-bold text-white">
                {initials(u.name)}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate font-medium text-slate-800 dark:text-slate-100">
                  {u.name}
                </span>
                <span className="block truncate text-xs text-slate-500">
                  {u.email}
                </span>
              </span>
              <span className="hidden sm:block">
                <span className="badge badge-brand">
                  {u.roles.join(", ") || "No role"}
                </span>
              </span>
              <span
                className={`badge ${u.is_active ? "badge-green" : "badge-red"}`}
              >
                {u.is_active ? "Active" : "Disabled"}
              </span>
              <span className="hidden w-24 text-right text-xs text-slate-500 md:block">
                {formatDate(u.created_at)}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

const GENDER_BAR: Record<string, string> = {
  male: "bg-blue-500",
  female: "bg-pink-500",
  other: "bg-amber-400",
};

/** Students by gender as one stacked bar with a legend. */
function StudentsByGenderCard({ data }: { data: StudentStatsWidgetData }) {
  const total = data.by_gender.reduce((sum, row) => sum + row.count, 0);
  return (
    <Card title="Students by gender" icon={ChartPie} tone="rose">
      {total === 0 ? (
        <p className={muted}>No students yet.</p>
      ) : (
        <>
          <div className="mb-4 flex h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
            {data.by_gender.map((row) =>
              row.count > 0 ? (
                <div
                  key={row.gender}
                  className={GENDER_BAR[row.gender] ?? "bg-slate-400"}
                  style={{ width: `${(row.count / total) * 100}%` }}
                />
              ) : null,
            )}
          </div>
          <ul className="space-y-2 text-sm">
            {data.by_gender.map((row) => (
              <li key={row.gender} className="flex items-center gap-2">
                <span
                  className={`h-2.5 w-2.5 rounded-full ${GENDER_BAR[row.gender] ?? "bg-slate-400"}`}
                />
                <span className="text-slate-700 dark:text-slate-200">
                  {row.label}
                </span>
                <span className="ml-auto font-medium tabular-nums">
                  {row.count}
                </span>
                <span className="w-12 text-right text-xs text-slate-500 tabular-nums">
                  {Math.round((row.count / total) * 100)}%
                </span>
              </li>
            ))}
          </ul>
        </>
      )}
    </Card>
  );
}

/** A parent's own children (the API scopes this to their guardian record). */
function ChildrenCard({ data }: { data: ChildrenWidgetData }) {
  return (
    <Card title="My children" icon={GraduationCap} tone="blue" wide>
      {!data.linked ? (
        <p className={muted}>
          Your login isn&apos;t linked to a guardian record yet. Please contact
          the school office.
        </p>
      ) : data.items.length === 0 ? (
        <p className={muted}>No children are linked to you yet.</p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {data.items.map((child) => (
            <li
              key={child.id}
              className="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800"
            >
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-blue-500 to-indigo-600 text-sm font-bold text-white">
                {initials(child.name)}
              </span>
              <span className="min-w-0">
                <span className="block truncate font-semibold text-slate-800 dark:text-slate-100">
                  {child.name}
                </span>
                <span className="block text-xs text-slate-500 tabular-nums">
                  ID {child.student_id}
                </span>
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
    <Card title="My subjects" icon={NotebookPen} tone="violet" wide>
      {!data.linked ? (
        <p className={muted}>
          Your login isn&apos;t linked to a teacher record yet. Please contact
          the school office.
        </p>
      ) : data.subjects.length === 0 && data.class_teacher_of.length === 0 ? (
        <p className={muted}>Nothing is assigned to you yet.</p>
      ) : (
        <div className="space-y-3 text-sm">
          {data.class_teacher_of.length > 0 && (
            <p className="flex flex-wrap items-center gap-2">
              <span className="text-slate-500">Class teacher of</span>
              {data.class_teacher_of.map((c) => (
                <span
                  key={`${c.class}-${c.section}`}
                  className="badge badge-brand"
                >
                  {c.class} {c.section}
                </span>
              ))}
            </p>
          )}
          <ul className="grid gap-2 sm:grid-cols-2">
            {data.subjects.map((s) => (
              <li
                key={`${s.class}-${s.section}-${s.subject}`}
                className="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/60"
              >
                <span className="font-medium text-slate-800 dark:text-slate-100">
                  {s.subject}
                </span>
                <span className="text-xs text-slate-500">
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

const pct = (value: number | null) => (value === null ? "—" : `${value}%`);

/** A teacher's sections with today's state; links straight to taking it. */
function AttendanceTasksCard({ data }: { data: AttendanceTasksWidgetData }) {
  const pending = data.sections.filter((s) => !s.marked).length;
  return (
    <Card
      title={
        pending > 0 ? `Attendance to take (${pending})` : "Attendance to take"
      }
      icon={ClipboardCheck}
      tone="emerald"
    >
      {!data.linked ? (
        <p className={muted}>
          Your login isn&apos;t linked to a teacher record yet.
        </p>
      ) : data.sections.length === 0 ? (
        <p className={muted}>You have no sections yet.</p>
      ) : (
        <ul className={list}>
          {data.sections.map((s) => (
            <li
              key={s.section_id}
              className="flex items-center justify-between gap-3 py-2.5"
            >
              <span className="font-medium text-slate-800 dark:text-slate-100">
                {s.class} {s.section}
              </span>
              {s.marked ? (
                <span className="badge badge-green">Taken today</span>
              ) : (
                <Link
                  href={`/teacher/attendance?section=${s.section_id}`}
                  className="btn btn-primary btn-sm"
                >
                  Take now
                </Link>
              )}
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

/** A teacher's mark sheets still open; links straight to each. */
function PendingMarksCard({ data }: { data: PendingMarksWidgetData }) {
  return (
    <Card
      title={
        data.total > 0 ? `Marks to enter (${data.total})` : "Marks to enter"
      }
      icon={ClipboardPen}
      tone="violet"
      action={
        <Link href="/teacher/marks" className="link">
          All sheets →
        </Link>
      }
    >
      {!data.linked ? (
        <p className={muted}>
          Your login isn&apos;t linked to a teacher record yet.
        </p>
      ) : data.sheets.length === 0 ? (
        <p className={muted}>All caught up: no marks waiting.</p>
      ) : (
        <ul className={list}>
          {data.sheets.map((s) => (
            <li
              key={`${s.exam_subject_id}-${s.section.id}`}
              className="flex items-center justify-between gap-3 py-2.5"
            >
              <span className="min-w-0">
                <span className="block truncate font-medium text-slate-800 dark:text-slate-100">
                  {s.class.name} {s.section.name} · {s.subject.name}
                </span>
                <span className="block text-xs text-slate-500 tabular-nums">
                  {s.exam.name} · {s.entered}/{s.students} entered
                </span>
              </span>
              <Link
                href={`/teacher/marks/sheet?exam=${s.exam.id}&paper=${s.exam_subject_id}&section=${s.section.id}`}
                className="btn btn-primary btn-sm"
              >
                {s.entered > 0 ? "Continue" : "Enter"}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

/** A student's (or each child's) latest published results. */
function PublishedResultsCard({
  data,
  parent,
}: {
  data: PublishedResultsWidgetData;
  parent: boolean;
}) {
  const href = parent ? "/parent/results" : "/student/results";
  const empty = data.students.every((s) => s.results.length === 0);
  return (
    <Card
      title={parent ? "Results" : "My results"}
      icon={Trophy}
      tone="amber"
      action={
        <Link href={href} className="link">
          All results →
        </Link>
      }
    >
      {!data.linked ? (
        <p className={muted}>
          Your login isn&apos;t linked to a {parent ? "guardian" : "student"}{" "}
          record yet.
        </p>
      ) : empty ? (
        <p className={muted}>No published results yet.</p>
      ) : (
        <ul className={list}>
          {data.students.flatMap((s) =>
            s.results.map((r) => (
              <li
                key={r.id}
                className="flex items-center justify-between gap-3 py-2.5"
              >
                <span className="min-w-0">
                  <span className="block truncate font-medium text-slate-800 dark:text-slate-100">
                    {r.exam.name}
                  </span>
                  <span className="block text-xs text-slate-500">
                    {parent ? `${s.student.name} · ` : ""}
                    {r.class} {r.section}
                    {r.position ? ` · Position ${r.position}` : ""}
                  </span>
                </span>
                <span className="text-right">
                  <span className="block text-lg leading-tight font-bold tabular-nums">
                    {r.gpa.toFixed(2)}
                  </span>
                  <span
                    className={`text-xs font-semibold ${r.passed ? "text-emerald-600 dark:text-emerald-400" : "text-rose-600 dark:text-rose-400"}`}
                  >
                    {r.grade}
                  </span>
                </span>
              </li>
            )),
          )}
        </ul>
      )}
    </Card>
  );
}

/** A student's (or each child's) fees: what's due and the unpaid months. */
function MyFeesCard({
  data,
  parent,
}: {
  data: MyFeesWidgetData;
  parent: boolean;
}) {
  const href = parent ? "/parent/fees" : "/student/fees";
  return (
    <Card
      title={parent ? "Fees & dues" : "My fees"}
      icon={Wallet}
      tone="rose"
      action={
        <Link href={href} className="link">
          All invoices →
        </Link>
      }
    >
      {!data.linked ? (
        <p className={muted}>
          Your login isn&apos;t linked to a {parent ? "guardian" : "student"}{" "}
          record yet.
        </p>
      ) : (
        <ul className="space-y-4">
          {data.students.map((s) => (
            <li key={s.student.id}>
              <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                <span className="font-medium text-slate-800 dark:text-slate-100">
                  {parent ? s.student.name : "Due now"}
                </span>
                <span
                  className={`text-lg font-bold tabular-nums ${Number(s.due) > 0 ? "text-rose-600 dark:text-rose-400" : "text-emerald-600 dark:text-emerald-400"}`}
                >
                  {formatMoney(s.due, data.currency)}
                </span>
              </div>
              {s.unpaid.length === 0 ? (
                <p className={muted}>All paid up.</p>
              ) : (
                <ul className="text-xs text-slate-500">
                  {s.unpaid.map((u) => (
                    <li
                      key={u.id}
                      className="flex justify-between gap-3 py-0.5"
                    >
                      <span>
                        {formatPeriod(u.period)} · due {formatDay(u.due_date)}
                      </span>
                      <span className="tabular-nums">
                        {formatMoney(u.due, data.currency)}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

/** A percentage as a thin coloured bar. */
function Meter({ value }: { value: number | null }) {
  const width = Math.max(0, Math.min(100, value ?? 0));
  const colour =
    width >= 90
      ? "bg-emerald-500"
      : width >= 75
        ? "bg-amber-400"
        : "bg-rose-500";
  return (
    <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
      <div
        className={`h-full rounded-full ${colour}`}
        style={{ width: `${width}%` }}
      />
    </div>
  );
}

/** Each of a parent's children: attendance % this year. */
function ChildAttendanceCard({ data }: { data: ChildAttendanceWidgetData }) {
  return (
    <Card
      title={`Attendance${data.academic_year ? ` · ${data.academic_year}` : ""}`}
      icon={CalendarCheck}
      tone="emerald"
    >
      {data.children.length === 0 ? (
        <p className={muted}>Nothing to show yet.</p>
      ) : (
        <ul className="space-y-4">
          {data.children.map((c) => (
            <li key={c.name}>
              <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                <span className="font-medium text-slate-800 dark:text-slate-100">
                  {c.name}
                </span>
                <span className="text-slate-500 tabular-nums">
                  <span className="text-base font-bold text-slate-900 dark:text-white">
                    {pct(c.percentage)}
                  </span>
                  {c.marked > 0 &&
                    ` · ${c.absent} absent of ${c.marked} ${c.marked === 1 ? "day" : "days"}`}
                </span>
              </div>
              <Meter value={c.percentage} />
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

/** A student's own attendance this year. */
function MyAttendanceCard({ data }: { data: MyAttendanceWidgetData }) {
  return (
    <Card
      title={`My attendance${data.academic_year ? ` · ${data.academic_year}` : ""}`}
      icon={CalendarCheck}
      tone="emerald"
    >
      {!data.summary || data.summary.marked === 0 ? (
        <p className={muted}>
          {data.linked
            ? "No attendance taken yet."
            : "Your login isn't linked to a student record yet."}
        </p>
      ) : (
        <div className="space-y-3">
          <p className="text-sm">
            <span className="text-4xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-white">
              {pct(data.summary.percentage)}
            </span>
          </p>
          <Meter value={data.summary.percentage} />
          <p className="text-sm text-slate-500 tabular-nums">
            {data.summary.absent} absent, {data.summary.late} late of{" "}
            {data.summary.marked} {data.summary.marked === 1 ? "day" : "days"}
          </p>
        </div>
      )}
    </Card>
  );
}

/** Today's periods; a teacher sees the section, a student the teacher. */
function TodaysPeriodsCard({
  title,
  data,
  forTeacher,
  link,
}: {
  title: string;
  data: TodaysPeriodsWidgetData;
  forTeacher: boolean;
  link: string;
}) {
  return (
    <Card
      title={title}
      icon={CalendarClock}
      tone="orange"
      action={
        <Link href={link} className="link">
          Full week →
        </Link>
      }
    >
      {data.periods.length === 0 ? (
        <p className={muted}>
          {data.linked ? "No periods today." : "Your login isn't linked yet."}
        </p>
      ) : (
        <ol className="relative space-y-3 border-l-2 border-orange-100 pl-4 dark:border-orange-500/20">
          {data.periods.map((p) => (
            <li key={p.id} className="relative">
              <span className="absolute top-1.5 -left-[21px] h-2.5 w-2.5 rounded-full bg-orange-500 ring-4 ring-white dark:ring-slate-900" />
              <p className="text-xs font-medium text-orange-600 tabular-nums dark:text-orange-300">
                {p.start_time}–{p.end_time}
              </p>
              <p className="font-medium text-slate-800 dark:text-slate-100">
                {p.label ?? p.subject?.name}
              </p>
              {!p.label && (
                <p className="text-xs text-slate-500">
                  {forTeacher
                    ? `${p.class.name} ${p.section.name}`
                    : p.teacher?.name}
                  {p.room && ` · Room ${p.room}`}
                </p>
              )}
            </li>
          ))}
        </ol>
      )}
    </Card>
  );
}

function UpcomingExamsCard({
  data,
  audience,
}: {
  data: UpcomingExamsWidgetData;
  audience: string;
}) {
  const admin = audience === "admin";
  const href = (id: number) =>
    admin ? `/admin/exams/${id}` : `/${audience}/exams`;
  return (
    <Card
      title="Upcoming exams"
      icon={NotebookPen}
      tone="indigo"
      action={
        <Link
          href={admin ? "/admin/exams" : `/${audience}/exams`}
          className="link"
        >
          All exams →
        </Link>
      }
    >
      {data.exams.length === 0 ? (
        <p className={muted}>No upcoming exams.</p>
      ) : (
        <ul className="space-y-2">
          {data.exams.map((e) => (
            <li key={e.id}>
              <Link
                href={href(e.id)}
                className="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:hover:border-indigo-500/40 dark:hover:bg-indigo-500/10"
              >
                <span className="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-indigo-600 text-white">
                  <span className="text-[10px] leading-none uppercase opacity-80">
                    {formatDay(e.start_date).split(" ")[1]}
                  </span>
                  <span className="text-sm leading-tight font-bold">
                    {formatDay(e.start_date).split(" ")[0]}
                  </span>
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-semibold text-slate-800 dark:text-slate-100">
                    {e.name}
                  </span>
                  <span className="block text-xs text-slate-500">
                    {e.end_date && e.end_date !== e.start_date
                      ? `${formatDay(e.start_date)} – ${formatDay(e.end_date)}`
                      : formatDay(e.start_date)}
                    {admin &&
                      e.classes !== undefined &&
                      ` · ${e.classes} ${e.classes === 1 ? "class" : "classes"}, ${e.papers} ${e.papers === 1 ? "paper" : "papers"}`}
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
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

function greeting(): string {
  const hour = new Date().getHours();
  return hour < 12
    ? "Good morning"
    : hour < 17
      ? "Good afternoon"
      : "Good evening";
}

/** Gradient banner with the greeting, school and session. */
function WelcomeHero({
  name,
  school,
}: {
  name?: string;
  school?: SchoolWidgetData;
}) {
  const today = new Date().toLocaleDateString("en-GB", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  });
  return (
    <section className="from-brand-600 shadow-brand-600/20 relative overflow-hidden rounded-3xl bg-linear-to-br via-indigo-600 to-violet-600 p-6 text-white shadow-xl sm:p-8">
      <span
        aria-hidden
        className="absolute -top-16 -right-10 h-56 w-56 rounded-full bg-white/10"
      />
      <span
        aria-hidden
        className="absolute right-28 -bottom-20 h-44 w-44 rounded-full bg-white/10"
      />
      <span
        aria-hidden
        className="absolute top-6 right-48 h-3 w-3 rounded-full bg-white/40"
      />
      <div className="relative flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-white/80">
            {greeting()} · {today}
          </p>
          <h1 className="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
            Welcome back{name ? `, ${name.split(" ")[0]}` : ""}
          </h1>
          {school && (
            <p className="mt-2 text-white/85">
              {school.name}
              {school.academic_year && ` · Session ${school.academic_year}`}
            </p>
          )}
        </div>
        <Sparkles aria-hidden className="h-10 w-10 text-white/40" />
      </div>
    </section>
  );
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
      <div className="p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    );
  }
  if (!payload) {
    return (
      <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div className="h-36 animate-pulse rounded-3xl bg-slate-200/70 dark:bg-slate-800" />
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-32 animate-pulse rounded-2xl bg-slate-200/70 dark:bg-slate-800"
            />
          ))}
        </div>
      </div>
    );
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
  const attendanceToday = find<AttendanceTodayWidgetData>("attendance_today");
  const attendanceTasks = find<AttendanceTasksWidgetData>("attendance_tasks");
  const childAttendance = find<ChildAttendanceWidgetData>("child_attendance");
  const myAttendance = find<MyAttendanceWidgetData>("my_attendance");
  const todaysClasses = find<TodaysPeriodsWidgetData>("todays_classes");
  const myRoutine = find<TodaysPeriodsWidgetData>("my_routine");
  const upcomingExams = find<UpcomingExamsWidgetData>("upcoming_exams");
  const pendingMarks = find<PendingMarksWidgetData>("pending_marks");
  const myResults = find<PublishedResultsWidgetData>("my_results");
  const childResults = find<PublishedResultsWidgetData>("child_results");
  const feeSummary = find<FeeSummaryWidgetData>("pending_fees");
  const myFees = find<MyFeesWidgetData>("my_fees");
  const childFees = find<MyFeesWidgetData>("child_fees");
  const others = ready.filter((w) => !CUSTOM_KEYS.has(w.key));

  const tiles: { key: string; tile: ReactNode }[] = [];
  if (students) {
    tiles.push({
      key: "students",
      tile: (
        <StatTile
          label="Students"
          value={students.total}
          icon={GraduationCap}
          tone="blue"
          hint={`+${students.admitted_this_month} admitted this month`}
        />
      ),
    });
  }
  if (teachers) {
    tiles.push({
      key: "teachers",
      tile: (
        <StatTile
          label="Teachers"
          value={teachers.total}
          icon={UserRound}
          tone="violet"
          hint={`${teachers.with_login} with a login · ${teachers.unassigned} unassigned`}
        />
      ),
    });
  }
  if (attendanceToday) {
    tiles.push(
      {
        key: "taken",
        tile: (
          <StatTile
            label="Attendance taken"
            value={`${attendanceToday.sections_marked}/${attendanceToday.sections_total}`}
            icon={CalendarCheck}
            tone="emerald"
            hint={
              attendanceToday.percentage === null
                ? "No sections taken yet today"
                : `${attendanceToday.percentage}% present so far`
            }
          />
        ),
      },
      {
        key: "absent",
        tile: (
          <StatTile
            label="Absent today"
            value={attendanceToday.absent}
            icon={UserX}
            tone="rose"
            hint={`${attendanceToday.late} late · ${attendanceToday.leave} on leave`}
          />
        ),
      },
    );
  }
  if (feeSummary) {
    tiles.push({
      key: "fees",
      tile: (
        <StatTile
          label="Fees outstanding"
          value={formatMoney(feeSummary.due, feeSummary.currency)}
          icon={Wallet}
          tone="amber"
          hint={`${formatMoney(feeSummary.collected, feeSummary.currency)} collected of ${formatMoney(feeSummary.billed, feeSummary.currency)}${feeSummary.overdue > 0 ? ` · ${feeSummary.overdue} overdue` : ""}`}
        />
      ),
    });
  }
  if (stats) {
    tiles.push({
      key: "users",
      tile: (
        <StatTile
          label="User accounts"
          value={stats.total}
          icon={Users}
          tone="cyan"
          hint={`${stats.active} active · ${stats.inactive} disabled`}
        />
      ),
    });
  }

  return (
    <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
      <WelcomeHero name={profile?.name} school={school} />

      {tiles.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {tiles.map((t) => (
            <div key={t.key}>{t.tile}</div>
          ))}
        </div>
      )}

      <div className="grid gap-5 md:grid-cols-2">
        {todaysClasses && (
          <TodaysPeriodsCard
            title="Today's classes"
            data={todaysClasses}
            forTeacher
            link="/teacher/routine"
          />
        )}
        {myRoutine && (
          <TodaysPeriodsCard
            title="Today's periods"
            data={myRoutine}
            forTeacher={false}
            link="/student/routine"
          />
        )}
        {upcomingExams && (
          <UpcomingExamsCard data={upcomingExams} audience={payload.audience} />
        )}
        {attendanceTasks && <AttendanceTasksCard data={attendanceTasks} />}
        {pendingMarks && <PendingMarksCard data={pendingMarks} />}
        {myResults && <PublishedResultsCard data={myResults} parent={false} />}
        {childResults && <PublishedResultsCard data={childResults} parent />}
        {myFees && <MyFeesCard data={myFees} parent={false} />}
        {childFees && <MyFeesCard data={childFees} parent />}
        {assigned && <AssignedSubjectsCard data={assigned} />}
        {childAttendance && <ChildAttendanceCard data={childAttendance} />}
        {myAttendance && <MyAttendanceCard data={myAttendance} />}
        {children && <ChildrenCard data={children} />}
        {students && <StudentsByGenderCard data={students} />}
        {stats && <UsersByRoleCard data={stats} />}
        {recent && <RecentUsersCard data={recent} />}
        {school && <SchoolCard data={school} />}
        {profile && <ProfileCard data={profile} />}
        {others.map((w) => (
          <GenericCard key={w.key} widget={w} />
        ))}
      </div>

      {pending.length > 0 && (
        <section>
          <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-300">
            <Hourglass className="h-4 w-4" />
            Coming as modules are built
          </h2>
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {pending.map((w) => (
              <li
                key={w.key}
                className="flex items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-white/50 p-3 text-sm dark:border-slate-700 dark:bg-slate-900/40"
              >
                <Sparkles className="text-brand-400 h-4 w-4 shrink-0" />
                <span className="min-w-0">
                  <span className="block truncate font-medium text-slate-700 dark:text-slate-200">
                    {w.title}
                  </span>
                  <span className="text-xs text-slate-500">
                    Module {w.module}
                  </span>
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

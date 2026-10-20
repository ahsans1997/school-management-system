import type { ReactNode } from "react";

/** Label/value rows for a profile; empty values show as "—". */
export function DetailsList({ rows }: { rows: [string, ReactNode][] }) {
  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
      {rows.map(([label, value]) => (
        <div key={label} className="contents">
          <dt className="text-slate-500 dark:text-slate-400">{label}</dt>
          <dd className="min-w-0 break-words">{value || "—"}</dd>
        </div>
      ))}
    </dl>
  );
}

import { GraduationCap } from "lucide-react";

/** A small spinning ring in the brand colour. */
export function Spinner({ className = "h-5 w-5" }: { className?: string }) {
  return (
    <span
      aria-hidden
      className={`border-brand-200 border-t-brand-600 dark:border-t-brand-400 inline-block animate-spin rounded-full border-2 dark:border-slate-700 ${className}`}
    />
  );
}

/** Loading inside a panel or below a page heading. */
export function InlineLoader({ label = "Loading…" }: { label?: string }) {
  return (
    <div
      role="status"
      aria-live="polite"
      className="flex items-center gap-3 py-6 text-sm text-slate-500 dark:text-slate-400"
    >
      <Spinner />
      <span>{label}</span>
    </div>
  );
}

const ROW_WIDTHS = ["62%", "48%", "71%", "55%", "66%", "44%"];

/**
 * A whole page loading: a skeleton of the usual layout (title, toolbar,
 * list), so the content appears in place instead of jumping in.
 */
export function PageLoader({ label = "Loading…" }: { label?: string }) {
  return (
    <div
      role="status"
      aria-live="polite"
      className="mx-auto max-w-6xl p-6 lg:p-8"
    >
      <span className="sr-only">{label}</span>

      <div className="mb-8 space-y-3">
        <div className="skeleton h-8 w-56 rounded-lg" />
        <div className="skeleton h-4 w-[28rem] max-w-full rounded-md" />
      </div>

      <div className="mb-5 flex flex-wrap gap-3">
        <div className="skeleton h-10 w-72 max-w-full rounded-lg" />
        <div className="skeleton h-10 w-24 rounded-lg" />
        <div className="skeleton ml-auto hidden h-10 w-32 rounded-lg sm:block" />
      </div>

      <div className="card overflow-hidden">
        <div className="flex gap-6 border-b border-slate-200 bg-slate-50/80 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/50">
          <div className="skeleton h-3 w-24 rounded" />
          <div className="skeleton h-3 w-20 rounded" />
          <div className="skeleton hidden h-3 w-16 rounded sm:block" />
        </div>
        {ROW_WIDTHS.map((width, i) => (
          <div
            key={i}
            className="flex items-center gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-slate-800"
          >
            <div className="skeleton h-9 w-9 shrink-0 rounded-full" />
            <div className="min-w-0 flex-1 space-y-2">
              <div
                className="skeleton h-3.5 rounded"
                style={{ width: width }}
              />
              <div className="skeleton h-3 w-1/4 rounded" />
            </div>
            <div className="skeleton hidden h-6 w-16 rounded-full sm:block" />
          </div>
        ))}
      </div>
    </div>
  );
}

/** Full-screen loader while the signed-in user is being checked. */
export function AppLoader() {
  return (
    <div
      role="status"
      aria-live="polite"
      className="flex min-h-screen flex-col items-center justify-center gap-5"
    >
      <div className="relative flex h-20 w-20 items-center justify-center">
        <span className="border-brand-100 border-t-brand-600 dark:border-t-brand-400 absolute inset-0 animate-spin rounded-full border-[3px] dark:border-slate-800" />
        <span className="from-brand-500 shadow-brand-600/30 flex h-12 w-12 items-center justify-center rounded-2xl bg-linear-to-br to-indigo-600 text-white shadow-lg">
          <GraduationCap className="h-6 w-6" />
        </span>
      </div>
      <p className="text-sm font-medium text-slate-500 dark:text-slate-400">
        Loading…
      </p>
    </div>
  );
}

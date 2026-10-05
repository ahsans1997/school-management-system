import type { ReactNode } from "react";

/** Shared look for text inputs and selects in record forms. */
export const fieldInputClass =
  "w-full rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800";

/**
 * Label + control + server error (or hint). Keep it at module level —
 * defining it inside a form component would remount inputs on every key.
 */
export function FormField({
  label,
  hint,
  error,
  wide = false,
  children,
}: {
  label: string;
  hint?: string;
  error?: string;
  wide?: boolean;
  children: ReactNode;
}) {
  return (
    <label className={`text-sm ${wide ? "sm:col-span-2" : ""}`}>
      <span className="mb-1 block font-medium">{label}</span>
      {children}
      {error ? (
        <span className="mt-1 block text-xs text-red-600">{error}</span>
      ) : hint ? (
        <span className="mt-1 block text-xs text-gray-500">{hint}</span>
      ) : null}
    </label>
  );
}

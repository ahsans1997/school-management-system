const compact = new Intl.NumberFormat("en", {
  notation: "compact",
  maximumFractionDigits: 1,
});
const plain = new Intl.NumberFormat("en");

/** Stat-tile value: full digits below 10k (1,284), compact above (12.9K). */
export function formatCount(value: number): string {
  return Math.abs(value) >= 10_000
    ? compact.format(value)
    : plain.format(value);
}

/** File size for humans: 512 B, 84 KB, 1.2 MB. */
export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

/**
 * Format a date-only value (YYYY-MM-DD). Built from local parts on purpose:
 * `new Date("2026-01-01")` parses as UTC midnight and shows the previous day
 * in timezones west of UTC.
 */
export function formatDay(day: string | null | undefined): string {
  if (!day) return "—";
  const [y, m, d] = day.split("-").map(Number);
  return new Date(y, m - 1, d).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

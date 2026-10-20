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

/** Today as YYYY-MM-DD in the user's own timezone (not UTC). */
export function localToday(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/**
 * Money for display: "1500.00" → "৳1,500.00" (South Asian grouping, so
 * 1,00,000). Amounts arrive as decimal strings and are only formatted here,
 * never added up on the client.
 */
export function formatMoney(
  amount: string | null | undefined,
  currency = "BDT",
): string {
  if (amount === null || amount === undefined || amount === "") return "—";
  try {
    return new Intl.NumberFormat("en-IN", {
      style: "currency",
      currency,
      currencyDisplay: "narrowSymbol",
      minimumFractionDigits: 2,
    }).format(Number(amount));
  } catch {
    return `${currency} ${amount}`;
  }
}

/** "2026-03" → "March 2026". */
export function formatPeriod(period: string | null | undefined): string {
  if (!period) return "—";
  const [y, m] = period.split("-").map(Number);
  return new Date(y, m - 1, 1).toLocaleDateString("en-GB", {
    month: "long",
    year: "numeric",
  });
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

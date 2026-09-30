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

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

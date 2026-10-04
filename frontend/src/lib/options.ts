/** Display label for an enum value from a `{ value, label }` option list. */
export function labelOf(
  options: readonly { value: string; label: string }[],
  value: string | null,
): string {
  return options.find((o) => o.value === value)?.label ?? "—";
}

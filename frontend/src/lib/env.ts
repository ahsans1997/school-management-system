/**
 * Public runtime configuration available in the browser bundle.
 * Only NEXT_PUBLIC_* variables are exposed client-side.
 */
export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api";

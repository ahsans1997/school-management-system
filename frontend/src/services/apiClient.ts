import { API_BASE_URL } from "@/lib/env";
import type { ApiError } from "@/types/api";

type JsonBody = Record<string, unknown> | unknown[] | null;

export interface RequestOptions extends Omit<RequestInit, "body"> {
  /** JSON-encoded, or sent as-is (multipart) when a FormData instance. */
  body?: JsonBody | FormData;
  /** JWT access token. Storage + refresh wiring lands in the Authentication module (01). */
  token?: string | null;
}

export class ApiRequestError extends Error {
  constructor(public readonly error: ApiError) {
    super(error.message);
    this.name = "ApiRequestError";
  }
}

/**
 * Minimal typed fetch wrapper for the Laravel API (base `/api`, no version segment).
 * The full JWT access/refresh + 401-retry flow is added in the Authentication module (01).
 */
export async function apiFetch<T>(
  path: string,
  { body, token, headers, ...init }: RequestOptions = {},
): Promise<T> {
  // FormData: let the browser set the multipart Content-Type + boundary.
  const isForm = body instanceof FormData;

  const res = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(body !== undefined && !isForm
        ? { "Content-Type": "application/json" }
        : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: isForm ? body : body !== undefined ? JSON.stringify(body) : undefined,
  });

  const isJson =
    res.headers.get("content-type")?.includes("application/json") ?? false;
  const payload = (isJson ? await res.json() : null) as
    | ({ message?: string; errors?: Record<string, string[]> } & Record<
        string,
        unknown
      >)
    | null;

  if (!res.ok) {
    throw new ApiRequestError({
      message: payload?.message ?? res.statusText,
      errors: payload?.errors,
      status: res.status,
    });
  }

  return payload as T;
}

/** Convenience call against the backend health probe. */
export function getHealth() {
  return apiFetch<{ status: string; service: string; time: string }>("/health");
}

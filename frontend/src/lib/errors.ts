import { ApiRequestError } from "@/services/apiClient";

/** First field-level validation error, else the API message, else a fallback. */
export function errorMessage(
  error: unknown,
  fallback = "Something went wrong.",
): string {
  if (error instanceof ApiRequestError) {
    const fieldError = error.error.errors
      ? Object.values(error.error.errors)[0]?.[0]
      : undefined;
    return fieldError ?? error.error.message;
  }
  return fallback;
}

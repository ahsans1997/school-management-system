import {
  apiFetch,
  ApiRequestError,
  type RequestOptions,
} from "@/services/apiClient";
import {
  clearTokens,
  getAccessToken,
  getRefreshToken,
  setTokens,
} from "@/lib/tokenStore";
import type { AuthTokenResponse, AuthUser } from "@/types/auth";

/** Authenticate with email + password; persists the returned token pair. */
export async function login(
  email: string,
  password: string,
): Promise<AuthUser> {
  const res = await apiFetch<AuthTokenResponse>("/auth/login", {
    method: "POST",
    body: { email, password },
  });
  setTokens({ access: res.access_token, refresh: res.refresh_token });
  return res.user;
}

/** Invalidate the access token server-side and clear local storage. */
export async function logout(): Promise<void> {
  try {
    await apiFetch("/auth/logout", {
      method: "POST",
      token: getAccessToken(),
    });
  } catch {
    /* logout should always clear locally, even if the call fails */
  } finally {
    clearTokens();
  }
}

let inFlightRefresh: Promise<void> | null = null;

/**
 * Exchange the refresh token for a new access + refresh pair. Concurrent
 * callers share one request: the server rotates (blacklists) the refresh
 * token on use, so a second exchange with the same token would fail.
 */
export function refresh(): Promise<void> {
  inFlightRefresh ??= exchangeRefreshToken().finally(() => {
    inFlightRefresh = null;
  });
  return inFlightRefresh;
}

async function exchangeRefreshToken(): Promise<void> {
  const refreshToken = getRefreshToken();
  if (!refreshToken) {
    throw new Error("No refresh token available.");
  }
  const res = await apiFetch<AuthTokenResponse>("/auth/refresh", {
    method: "POST",
    token: refreshToken,
  });
  setTokens({ access: res.access_token, refresh: res.refresh_token });
}

/** Current authenticated user. */
export async function me(): Promise<AuthUser> {
  const res = await authedFetch<{ user: AuthUser }>("/auth/me");
  return res.user;
}

/**
 * Authenticated request helper: attaches the access token and, on a 401,
 * silently refreshes once and retries. On refresh failure, tokens are
 * cleared and the error propagates — unless another request or tab has
 * already stored a fresh pair, in which case that one is used.
 */
export async function authedFetch<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const sentToken = getAccessToken();
  try {
    return await apiFetch<T>(path, { ...options, token: sentToken });
  } catch (error) {
    const canRetry =
      error instanceof ApiRequestError &&
      error.error.status === 401 &&
      Boolean(getRefreshToken());

    if (!canRetry) throw error;

    // Someone refreshed while this request was in flight: just retry.
    if (getAccessToken() === sentToken) {
      const usedRefreshToken = getRefreshToken();
      try {
        await refresh();
      } catch (refreshError) {
        if (getRefreshToken() === usedRefreshToken) {
          clearTokens();
          throw refreshError;
        }
        // Another tab rotated the pair meanwhile; fall through and retry with it.
      }
    }

    return apiFetch<T>(path, { ...options, token: getAccessToken() });
  }
}

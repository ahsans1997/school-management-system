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

/** Exchange the refresh token for a new access + refresh pair. */
export async function refresh(): Promise<void> {
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
 * cleared and the error propagates.
 */
export async function authedFetch<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  try {
    return await apiFetch<T>(path, { ...options, token: getAccessToken() });
  } catch (error) {
    const canRetry =
      error instanceof ApiRequestError &&
      error.error.status === 401 &&
      Boolean(getRefreshToken());

    if (!canRetry) throw error;

    try {
      await refresh();
    } catch (refreshError) {
      clearTokens();
      throw refreshError;
    }

    return apiFetch<T>(path, { ...options, token: getAccessToken() });
  }
}

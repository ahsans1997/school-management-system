import type { TokenPair } from "@/types/auth";

/**
 * Per-viewer token storage in localStorage. Client-only; every access is
 * guarded so SSR and private-mode/blocked-storage never throw.
 *
 * Note: localStorage is readable by any script on the origin (XSS surface).
 * Acceptable for V1; a future hardening step can move to httpOnly cookies.
 */
const ACCESS_KEY = "sms.access_token";
const REFRESH_KEY = "sms.refresh_token";

function safeGet(key: string): string | null {
  try {
    return typeof window !== "undefined"
      ? window.localStorage.getItem(key)
      : null;
  } catch {
    return null;
  }
}

function safeSet(key: string, value: string): void {
  try {
    if (typeof window !== "undefined") window.localStorage.setItem(key, value);
  } catch {
    /* ignore write failures (private mode, quota) */
  }
}

function safeRemove(key: string): void {
  try {
    if (typeof window !== "undefined") window.localStorage.removeItem(key);
  } catch {
    /* ignore */
  }
}

export function getAccessToken(): string | null {
  return safeGet(ACCESS_KEY);
}

export function getRefreshToken(): string | null {
  return safeGet(REFRESH_KEY);
}

export function setTokens(tokens: TokenPair): void {
  safeSet(ACCESS_KEY, tokens.access);
  safeSet(REFRESH_KEY, tokens.refresh);
}

export function clearTokens(): void {
  safeRemove(ACCESS_KEY);
  safeRemove(REFRESH_KEY);
}

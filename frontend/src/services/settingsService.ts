import { authedFetch } from "@/services/authService";
import type { SchoolSettings } from "@/types/settings";

export function getSettings(): Promise<SchoolSettings> {
  return authedFetch<{ data: SchoolSettings }>("/settings").then((r) => r.data);
}

/** Partial update. Secret keys left blank are kept server-side. */
export function updateSettings(
  payload: Record<string, unknown>,
): Promise<SchoolSettings> {
  return authedFetch<{ data: SchoolSettings }>("/settings", {
    method: "PUT",
    body: payload,
  }).then((r) => r.data);
}

function upload(path: string, file: File): Promise<SchoolSettings> {
  const form = new FormData();
  form.append("file", file);
  return authedFetch<{ data: SchoolSettings }>(path, {
    method: "POST",
    body: form,
  }).then((r) => r.data);
}

export const uploadLogo = (file: File) => upload("/settings/logo", file);
export const uploadFavicon = (file: File) => upload("/settings/favicon", file);

/** Client-side hints mirroring the server rules (the server stays authoritative). */
export const UPLOAD_RULES = {
  logo: {
    types: ["image/png", "image/jpeg", "image/webp"],
    maxKb: 2048,
    accept: ".png,.jpg,.jpeg,.webp",
  },
  favicon: {
    types: ["image/png", "image/x-icon", "image/vnd.microsoft.icon"],
    maxKb: 512,
    accept: ".png,.ico",
  },
} as const;

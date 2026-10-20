"use client";

import { useRef } from "react";

/** Matches the API: JPEG/PNG/WebP up to 2 MB. */
const ACCEPT = "image/jpeg,image/png,image/webp";
const MAX_BYTES = 2 * 1024 * 1024;

interface Props {
  photoUrl: string | null;
  alt: string;
  canEdit: boolean;
  busy: boolean;
  onUpload: (file: File) => void;
  onRemove: () => void;
  onError: (message: string) => void;
}

/** Profile photo (signed link) with upload / replace / remove controls. */
export function PhotoEditor({
  photoUrl,
  alt,
  canEdit,
  busy,
  onUpload,
  onRemove,
  onError,
}: Props) {
  const input = useRef<HTMLInputElement>(null);

  function choose(file: File | undefined) {
    if (!file) return;
    if (file.size > MAX_BYTES) onError("The photo must be 2 MB or smaller.");
    else onUpload(file);
    if (input.current) input.current.value = "";
  }

  return (
    <div className="shrink-0">
      {photoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={photoUrl}
          alt={alt}
          className="h-28 w-24 rounded-md border border-slate-200 object-cover dark:border-slate-800"
        />
      ) : (
        <div className="flex h-28 w-24 items-center justify-center rounded-md border border-dashed border-slate-300 text-xs text-slate-500 dark:border-slate-700">
          No photo
        </div>
      )}
      {canEdit && (
        <div className="mt-2 flex flex-col gap-1 text-xs">
          <label className="cursor-pointer hover:underline">
            {photoUrl ? "Replace photo" : "Upload photo"}
            <input
              ref={input}
              type="file"
              accept={ACCEPT}
              className="sr-only"
              disabled={busy}
              onChange={(e) => choose(e.target.files?.[0])}
            />
          </label>
          {photoUrl && (
            <button
              type="button"
              disabled={busy}
              onClick={onRemove}
              className="text-left text-red-600 hover:underline"
            >
              Remove photo
            </button>
          )}
        </div>
      )}
    </div>
  );
}

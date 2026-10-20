"use client";

import { useRef, useState, type FormEvent } from "react";
import { errorMessage } from "@/lib/errors";
import { formatBytes, formatDate } from "@/lib/format";
import type { StoredDocument } from "@/types/document";

const inputClass = "input input-sm";

/** Matches the API: PDF or image, up to 5 MB. */
const ACCEPT = "application/pdf,image/jpeg,image/png,image/webp";
const MAX_BYTES = 5 * 1024 * 1024;

interface Props {
  documents: StoredDocument[];
  /** Edit permission and not archived. */
  canEdit: boolean;
  upload: (title: string, file: File) => Promise<StoredDocument>;
  remove: (doc: StoredDocument) => Promise<unknown>;
  onChange: (documents: StoredDocument[]) => void;
}

/** Documents on a record: download (signed link), upload, delete. */
export function DocumentsPanel({
  documents,
  canEdit,
  upload,
  remove,
  onChange,
}: Props) {
  const fileInput = useRef<HTMLInputElement>(null);
  const [title, setTitle] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleUpload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const file = fileInput.current?.files?.[0];
    if (!file) return;
    if (file.size > MAX_BYTES) {
      setError("The file must be 5 MB or smaller.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const doc = await upload(title.trim(), file);
      onChange([doc, ...documents]);
      setTitle("");
      if (fileInput.current) fileInput.current.value = "";
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function handleDelete(doc: StoredDocument) {
    if (!window.confirm(`Delete “${doc.title}”? This cannot be undone.`))
      return;
    setBusy(true);
    setError(null);
    try {
      await remove(doc);
      onChange(documents.filter((d) => d.id !== doc.id));
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="card p-5">
      <h2 className="mb-3 text-sm font-semibold">Documents</h2>

      {error && (
        <p role="alert" className="alert alert-error mb-3">
          {error}
        </p>
      )}

      {documents.length === 0 ? (
        <p className="mb-3 text-sm text-slate-500">No documents yet.</p>
      ) : (
        <ul className="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
          {documents.map((doc) => (
            <li
              key={doc.id}
              className="flex flex-wrap items-center gap-x-3 py-2 text-sm"
            >
              <span className="min-w-0 flex-1">
                <a
                  href={doc.download_url}
                  className="font-medium hover:underline"
                >
                  {doc.title}
                </a>
                <span className="block truncate text-xs text-slate-500">
                  {doc.original_name} · {formatBytes(doc.size)} ·{" "}
                  {formatDate(doc.created_at)}
                </span>
              </span>
              {canEdit && (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => handleDelete(doc)}
                  className="text-xs text-red-600 hover:underline"
                >
                  Delete
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      {canEdit && (
        <form
          onSubmit={handleUpload}
          aria-label="Upload document"
          className="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
        >
          <input
            required
            maxLength={100}
            aria-label="Document title"
            placeholder="Title, e.g. Birth certificate"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className={`min-w-48 flex-1 ${inputClass}`}
          />
          <input
            ref={fileInput}
            type="file"
            required
            accept={ACCEPT}
            aria-label="Document file"
            className="max-w-full text-sm"
          />
          <button type="submit" disabled={busy} className="btn btn-primary">
            Upload
          </button>
          <span className="w-full text-xs text-slate-500">
            PDF or image, up to 5 MB.
          </span>
        </form>
      )}
    </section>
  );
}

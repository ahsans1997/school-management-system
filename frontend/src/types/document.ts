/** A file kept on a record (student, teacher…). Named so it doesn't clash with the DOM's Document. */
export interface StoredDocument {
  id: number;
  title: string;
  original_name: string;
  mime_type: string;
  size: number;
  /** Expiring signed link — no auth header needed. */
  download_url: string;
  created_at: string | null;
}

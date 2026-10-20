"use client";

import {
  useEffect,
  useState,
  type ChangeEvent,
  type FormEvent,
  type ReactNode,
} from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { ApiRequestError } from "@/services/apiClient";
import * as settingsService from "@/services/settingsService";
import type { SchoolSettings, SecretGroupName } from "@/types/settings";
import { PageLoader } from "@/components/Loading";

function messageOf(error: unknown): string {
  if (error instanceof ApiRequestError) {
    const fieldError = error.error.errors
      ? Object.values(error.error.errors)[0]?.[0]
      : undefined;
    return fieldError ?? error.error.message;
  }
  return "Something went wrong.";
}

interface FieldDef {
  key: string;
  label: string;
  type?: string;
  secret?: boolean;
}

const IDENTITY_FIELDS: FieldDef[] = [
  { key: "name", label: "School name" },
  { key: "principal_name", label: "Principal" },
  { key: "email", label: "Email", type: "email" },
  { key: "phone", label: "Phone" },
  { key: "website", label: "Website", type: "url" },
  { key: "address", label: "Address" },
  { key: "eiin", label: "EIIN" },
  { key: "school_code", label: "School code" },
  { key: "established_year", label: "Established year", type: "number" },
];

const SYSTEM_FIELDS: FieldDef[] = [
  { key: "currency", label: "Currency (ISO code, e.g. BDT)" },
  { key: "timezone", label: "Timezone (e.g. Asia/Dhaka)" },
  { key: "date_format", label: "Date format (e.g. d/m/Y)" },
  { key: "language", label: "Language (e.g. en)" },
];

const SOCIAL_KEYS = ["facebook", "youtube", "instagram", "linkedin"];

const GROUPS: { name: SecretGroupName; title: string; fields: FieldDef[] }[] = [
  {
    name: "email_settings",
    title: "Email (SMTP)",
    fields: [
      { key: "host", label: "SMTP host" },
      { key: "port", label: "Port" },
      { key: "username", label: "Username" },
      { key: "encryption", label: "Encryption (tls / ssl)" },
      { key: "from_address", label: "From address" },
      { key: "password", label: "Password", secret: true },
    ],
  },
  {
    name: "sms_settings",
    title: "SMS",
    fields: [
      { key: "provider", label: "Provider" },
      { key: "sender_id", label: "Sender ID" },
      { key: "api_key", label: "API key", secret: true },
    ],
  },
  {
    name: "payment_settings",
    title: "Payment gateway",
    fields: [
      { key: "provider", label: "Gateway" },
      { key: "merchant_id", label: "Merchant ID" },
      { key: "api_key", label: "API key", secret: true },
      { key: "secret_key", label: "Secret key", secret: true },
    ],
  },
];

type Flat = Record<string, string>;
type Groups = Record<SecretGroupName, Flat>;

const inputClass = "w-full input input-sm";

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-medium text-slate-700 dark:text-slate-300">
        {label}
      </span>
      {children}
    </label>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <fieldset className="card p-5">
      <legend className="px-1 text-sm font-semibold">{title}</legend>
      <div className="grid gap-3 sm:grid-cols-2">{children}</div>
    </fieldset>
  );
}

function toFlat(s: SchoolSettings): Flat {
  const flat: Flat = {};
  for (const f of [...IDENTITY_FIELDS, ...SYSTEM_FIELDS]) {
    const value = s[f.key as keyof SchoolSettings];
    flat[f.key] = value === null || value === undefined ? "" : String(value);
  }
  return flat;
}

function toGroups(s: SchoolSettings): Groups {
  const groups = {} as Groups;
  for (const g of GROUPS) {
    groups[g.name] = {};
    for (const f of g.fields) {
      // Secrets always start blank — the server never sends them back.
      groups[g.name][f.key] = f.secret ? "" : String(s[g.name][f.key] ?? "");
    }
  }
  return groups;
}

export function SettingsForm() {
  const { can } = useAuth();
  const canManage = can("settings.manage");
  const [settings, setSettings] = useState<SchoolSettings | null>(null);
  const [flat, setFlat] = useState<Flat>({});
  const [socials, setSocials] = useState<Flat>({});
  const [groups, setGroups] = useState<Groups | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  function hydrate(s: SchoolSettings) {
    setSettings(s);
    setFlat(toFlat(s));
    setSocials(
      Object.fromEntries(SOCIAL_KEYS.map((k) => [k, s.socials?.[k] ?? ""])),
    );
    setGroups(toGroups(s));
  }

  useEffect(() => {
    settingsService
      .getSettings()
      .then(hydrate)
      .catch((err) => setError(messageOf(err)));
  }, []);

  async function handleSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!groups) return;
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const payload: Record<string, unknown> = {
        ...flat,
        established_year: flat.established_year
          ? Number(flat.established_year)
          : null,
        socials: Object.fromEntries(
          Object.entries(socials).filter(([, v]) => v.trim() !== ""),
        ),
        ...groups,
      };
      hydrate(await settingsService.updateSettings(payload));
      setNotice("Settings saved.");
    } catch (err) {
      setError(messageOf(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleUpload(
    kind: "logo" | "favicon",
    event: ChangeEvent<HTMLInputElement>,
  ) {
    const file = event.target.files?.[0];
    event.target.value = ""; // allow re-selecting the same file
    if (!file) return;

    const rule = settingsService.UPLOAD_RULES[kind];
    const extOk = rule.accept
      .split(",")
      .some((ext) => file.name.toLowerCase().endsWith(ext));
    const typeOk =
      (rule.types as readonly string[]).includes(file.type) ||
      (file.type === "" && extOk);

    setError(null);
    setNotice(null);
    if (!typeOk || !extOk) {
      setError(`Unsupported file type. Allowed: ${rule.accept}`);
      return;
    }
    if (file.size > rule.maxKb * 1024) {
      setError(`File too large. Maximum is ${rule.maxKb} KB.`);
      return;
    }

    try {
      const updated =
        kind === "logo"
          ? await settingsService.uploadLogo(file)
          : await settingsService.uploadFavicon(file);
      setSettings(updated); // keep any unsaved form edits
      setNotice(`${kind === "logo" ? "Logo" : "Favicon"} uploaded.`);
    } catch (err) {
      setError(messageOf(err));
    }
  }

  if (!settings || !groups) {
    return error ? (
      <div className="p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  return (
    <form onSubmit={handleSave} className="mx-auto max-w-4xl space-y-6 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">School Settings</h1>
        {canManage && (
          <button type="submit" disabled={saving} className="btn btn-primary">
            {saving ? "Saving…" : "Save"}
          </button>
        )}
      </div>

      {!canManage && (
        <p className="text-sm text-slate-500">
          Read-only — you need the settings.manage permission to edit.
        </p>
      )}
      {error && (
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      )}
      {notice && <p className="alert alert-success">{notice}</p>}

      <Section title="Branding">
        {(["logo", "favicon"] as const).map((kind) => {
          const url =
            kind === "logo" ? settings.logo_url : settings.favicon_url;
          const rule = settingsService.UPLOAD_RULES[kind];
          return (
            <div key={kind} className="space-y-2 text-sm">
              <span className="block font-medium text-slate-700 capitalize dark:text-slate-300">
                {kind}
              </span>
              <div className="flex h-16 items-center">
                {url ? (
                  // Dynamic asset served from the API host.
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={url}
                    alt={`${kind} preview`}
                    className="max-h-16 max-w-40 object-contain"
                  />
                ) : (
                  <span className="text-slate-400">None uploaded</span>
                )}
              </div>
              {canManage && (
                <>
                  <input
                    type="file"
                    aria-label={`Upload ${kind}`}
                    accept={rule.accept}
                    onChange={(e) => handleUpload(kind, e)}
                    className="block text-xs"
                  />
                  <p className="text-xs text-slate-500">
                    {rule.accept} · max {rule.maxKb} KB
                  </p>
                </>
              )}
            </div>
          );
        })}
      </Section>

      <Section title="School information">
        {IDENTITY_FIELDS.map((f) => (
          <Field key={f.key} label={f.label}>
            <input
              type={f.type ?? "text"}
              required={f.key === "name"}
              disabled={!canManage}
              value={flat[f.key] ?? ""}
              onChange={(e) => setFlat({ ...flat, [f.key]: e.target.value })}
              className={inputClass}
            />
          </Field>
        ))}
      </Section>

      <Section title="Social links">
        {SOCIAL_KEYS.map((key) => (
          <Field key={key} label={key}>
            <input
              type="url"
              placeholder="https://…"
              disabled={!canManage}
              value={socials[key] ?? ""}
              onChange={(e) =>
                setSocials({ ...socials, [key]: e.target.value })
              }
              className={inputClass}
            />
          </Field>
        ))}
      </Section>

      <Section title="System">
        {SYSTEM_FIELDS.map((f) => (
          <Field key={f.key} label={f.label}>
            <input
              disabled={!canManage}
              value={flat[f.key] ?? ""}
              onChange={(e) => setFlat({ ...flat, [f.key]: e.target.value })}
              className={inputClass}
            />
          </Field>
        ))}
      </Section>

      {GROUPS.map((g) => (
        <Section key={g.name} title={g.title}>
          {g.fields.map((f) => {
            const isSet = Boolean(settings[g.name][`${f.key}_set`]);
            return (
              <Field key={f.key} label={f.label}>
                <input
                  type={f.secret ? "password" : "text"}
                  autoComplete={f.secret ? "new-password" : "off"}
                  disabled={!canManage}
                  placeholder={
                    f.secret
                      ? isSet
                        ? "•••••• set — leave blank to keep"
                        : "Not set"
                      : ""
                  }
                  value={groups[g.name][f.key] ?? ""}
                  onChange={(e) =>
                    setGroups({
                      ...groups,
                      [g.name]: { ...groups[g.name], [f.key]: e.target.value },
                    })
                  }
                  className={inputClass}
                />
              </Field>
            );
          })}
        </Section>
      ))}
    </form>
  );
}

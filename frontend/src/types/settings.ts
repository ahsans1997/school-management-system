/**
 * A secret settings group as returned by the API: plain config keys come
 * back as-is, every secret is replaced by a `<key>_set` boolean.
 */
export type SecretGroup = Record<string, string | boolean | null>;

export interface SchoolSettings {
  name: string;
  logo_url: string | null;
  favicon_url: string | null;
  address: string | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  eiin: string | null;
  school_code: string | null;
  established_year: number | null;
  principal_name: string | null;
  socials: Record<string, string>;
  currency: string;
  timezone: string;
  date_format: string;
  language: string;
  email_settings: SecretGroup;
  sms_settings: SecretGroup;
  payment_settings: SecretGroup;
  active_academic_year_id: number | null;
  updated_at?: string;
}

export type SecretGroupName =
  "email_settings" | "sms_settings" | "payment_settings";

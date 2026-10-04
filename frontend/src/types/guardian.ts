/** A guardian's Parent-portal login (a user with the Parent role). */
export interface GuardianAccount {
  id: number;
  email: string;
  is_active: boolean;
}

export interface Guardian {
  id: number;
  name: string;
  phone: string;
  /** Contact address — separate from the login email. */
  email: string | null;
  occupation: string | null;
  address: string | null;
  account: GuardianAccount | null;
  created_at?: string;
}

export interface GuardianInput {
  name: string;
  phone: string;
  email: string | null;
  occupation: string | null;
  address: string | null;
}

/** Credentials for a new Parent login; the role is fixed by the server. */
export interface AccountInput {
  email: string;
  password: string;
}

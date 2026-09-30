/** Which area/dashboard a user belongs to — decided by the server. */
export type Audience = "admin" | "staff" | "teacher" | "parent" | "student";

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  roles: string[];
  permissions: string[];
  audience: Audience;
  created_at?: string;
}

export interface TokenPair {
  access: string;
  refresh: string;
}

/** Response shape for /auth/login and /auth/refresh. */
export interface AuthTokenResponse {
  user: AuthUser;
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
}

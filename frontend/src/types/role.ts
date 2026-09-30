export interface Role {
  id: number;
  name: string;
  guard_name: string;
  is_system: boolean;
  permissions?: string[];
  created_at?: string;
}

export interface PermissionGroup {
  group: string;
  permissions: string[];
}

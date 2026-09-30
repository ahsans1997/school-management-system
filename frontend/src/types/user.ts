export interface ManagedUser {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  roles: string[];
  personable_type: string | null;
  personable_id: number | null;
  created_at?: string;
}

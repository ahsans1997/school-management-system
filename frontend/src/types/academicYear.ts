export interface AcademicYear {
  id: number;
  name: string;
  /** ISO date, YYYY-MM-DD. */
  start_date: string;
  end_date: string;
  is_current: boolean;
  created_at?: string;
}

export interface AcademicYearInput {
  name: string;
  start_date: string;
  end_date: string;
}

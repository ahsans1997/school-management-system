/** Shared API response shapes returned by the Laravel backend. */

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
  status: number;
}

/** Standard Laravel paginator envelope for list endpoints. */
export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

"use client";

interface PaginationProps {
  page: number;
  lastPage: number;
  onPage: (page: number) => void;
}

/** Prev / "Page x of y" / Next. Renders nothing when there's one page. */
export function Pagination({ page, lastPage, onPage }: PaginationProps) {
  if (lastPage <= 1) return null;

  const button = "btn btn-secondary";

  return (
    <nav
      aria-label="Pagination"
      className="mt-4 flex items-center gap-3 text-sm"
    >
      <button
        type="button"
        disabled={page <= 1}
        onClick={() => onPage(page - 1)}
        className={button}
      >
        Prev
      </button>
      <span className="text-slate-500">
        Page {page} of {lastPage}
      </span>
      <button
        type="button"
        disabled={page >= lastPage}
        onClick={() => onPage(page + 1)}
        className={button}
      >
        Next
      </button>
    </nav>
  );
}

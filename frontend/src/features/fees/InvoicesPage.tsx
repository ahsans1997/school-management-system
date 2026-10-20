"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { PageLoader } from "@/components/Loading";
import { Pagination } from "@/components/Pagination";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatMoney, formatPeriod } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as feeService from "@/services/feeService";
import {
  INVOICE_STATUSES,
  type FeeOptions,
  type InvoiceFilters,
  type InvoicePage,
  type InvoiceStatus,
} from "@/types/fee";
import { GeneratePanel } from "./GeneratePanel";

const inputClass = "input input-sm";

export const INVOICE_BADGE: Record<InvoiceStatus, string> = {
  unpaid: "badge badge-amber",
  partial: "badge badge-brand",
  paid: "badge badge-green",
  void: "badge badge-gray",
};

/** Invoices: filters, totals, the list, and background generation. */
export function InvoicesPage() {
  const { can } = useAuth();
  const [options, setOptions] = useState<FeeOptions | null>(null);
  const [filters, setFilters] = useState<InvoiceFilters>({ status: "" });
  const [search, setSearch] = useState("");
  const [page, setPage] = useState<InvoicePage | null>(null);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    Promise.all([feeService.getOptions(), feeService.listInvoices()])
      .then(([o, p]) => {
        if (!active) return;
        setOptions(o);
        setPage(p);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  const load = useCallback(async (next: InvoiceFilters) => {
    setError(null);
    try {
      setPage(await feeService.listInvoices(next));
      setFilters(next);
    } catch (err) {
      setError(errorMessage(err));
    }
  }, []);

  const refresh = useCallback(
    () => void load({ ...filters, page: 1 }),
    [load, filters],
  );

  if (!options || !page) {
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

  const klass = options.classes.find((c) => c.id === filters.classId);
  const set = (patch: Partial<InvoiceFilters>) =>
    void load({ ...filters, ...patch, page: 1 });

  return (
    <div className="mx-auto max-w-6xl p-6">
      <div className="mb-1 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-semibold">Invoices</h1>
        {can("fee.create") && !generating && (
          <button
            type="button"
            onClick={() => setGenerating(true)}
            className="btn btn-primary"
          >
            Generate invoices
          </button>
        )}
      </div>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        One invoice per student per month, built from the class&apos;s fees.
        Totals and dues are worked out by the server.
      </p>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}

      {generating && (
        <GeneratePanel
          options={options}
          onDone={refresh}
          onClose={() => setGenerating(false)}
        />
      )}

      <div className="mb-4 flex flex-wrap items-center gap-2">
        <select
          aria-label="Academic year"
          value={filters.academicYearId ?? ""}
          onChange={(e) =>
            set({
              academicYearId: e.target.value ? Number(e.target.value) : null,
            })
          }
          className={inputClass}
        >
          <option value="">All years</option>
          {options.academic_years.map((y) => (
            <option key={y.id} value={y.id}>
              {y.name}
            </option>
          ))}
        </select>
        <select
          aria-label="Class"
          value={filters.classId ?? ""}
          onChange={(e) =>
            set({
              classId: e.target.value ? Number(e.target.value) : null,
              sectionId: null,
            })
          }
          className={inputClass}
        >
          <option value="">All classes</option>
          {options.classes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        {klass && klass.sections.length > 1 && (
          <select
            aria-label="Section"
            value={filters.sectionId ?? ""}
            onChange={(e) =>
              set({ sectionId: e.target.value ? Number(e.target.value) : null })
            }
            className={inputClass}
          >
            <option value="">All sections</option>
            {klass.sections.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
        )}
        <input
          type="month"
          aria-label="Month"
          value={filters.period ?? ""}
          onChange={(e) => set({ period: e.target.value })}
          className={inputClass}
        />
        <select
          aria-label="Status"
          value={filters.status ?? ""}
          onChange={(e) =>
            set({ status: e.target.value as InvoiceFilters["status"] })
          }
          className={inputClass}
        >
          <option value="">Any status</option>
          <option value="outstanding">Outstanding</option>
          {INVOICE_STATUSES.map((s) => (
            <option key={s.value} value={s.value}>
              {s.label}
            </option>
          ))}
        </select>
        <form
          role="search"
          onSubmit={(e) => {
            e.preventDefault();
            set({ search: search.trim() });
          }}
          className="flex gap-2"
        >
          <input
            type="search"
            aria-label="Search invoices"
            placeholder="Name, student ID or invoice no."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className={`w-56 ${inputClass}`}
          />
          <button type="submit" className="btn btn-secondary btn-sm">
            Search
          </button>
        </form>
      </div>

      <div className="mb-4 grid gap-3 sm:grid-cols-3">
        {(
          [
            [
              "Billed",
              page.totals.billed,
              "text-slate-900 dark:text-slate-100",
            ],
            [
              "Collected",
              page.totals.collected,
              "text-emerald-700 dark:text-emerald-400",
            ],
            [
              "Outstanding",
              page.totals.due,
              "text-amber-700 dark:text-amber-400",
            ],
          ] as const
        ).map(([label, amount, tone]) => (
          <div key={label} className="card px-4 py-3">
            <p className="text-xs text-slate-500">{label}</p>
            <p className={`text-lg font-semibold tabular-nums ${tone}`}>
              {formatMoney(amount, page.currency)}
            </p>
          </div>
        ))}
      </div>

      {page.data.length === 0 ? (
        <p className="text-sm text-slate-500">No invoices match.</p>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr className="table-head">
                <th className="py-2 pr-3 font-medium">Invoice</th>
                <th className="py-2 pr-3 font-medium">Student</th>
                <th className="py-2 pr-3 font-medium">Class</th>
                <th className="py-2 pr-3 font-medium">Month</th>
                <th className="py-2 pr-3 text-right font-medium">Total</th>
                <th className="py-2 pr-3 text-right font-medium">Due</th>
                <th className="py-2 pr-3 font-medium">Status</th>
                <th className="py-2 font-medium">
                  <span className="sr-only">Open</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {page.data.map((i) => (
                <tr key={i.id} className="table-row">
                  <td className="py-2 pr-3 font-mono text-xs">
                    {i.invoice_no}
                  </td>
                  <td className="py-2 pr-3">
                    <span className="font-medium">{i.student?.name}</span>
                    <span className="block text-xs text-slate-500 tabular-nums">
                      {i.student?.student_id}
                    </span>
                  </td>
                  <td className="py-2 pr-3 whitespace-nowrap">
                    {i.class?.name} {i.section?.name}
                  </td>
                  <td className="py-2 pr-3 whitespace-nowrap">
                    {formatPeriod(i.period)}
                  </td>
                  <td className="py-2 pr-3 text-right tabular-nums">
                    {formatMoney(i.total, page.currency)}
                  </td>
                  <td className="py-2 pr-3 text-right font-semibold tabular-nums">
                    {i.status === "void"
                      ? "—"
                      : formatMoney(i.due, page.currency)}
                  </td>
                  <td className="py-2 pr-3">
                    <span className={INVOICE_BADGE[i.status]}>
                      {labelOf(INVOICE_STATUSES, i.status)}
                    </span>
                  </td>
                  <td className="py-2 text-right">
                    <Link
                      href={`/admin/fees/invoices/${i.id}`}
                      className="btn btn-secondary btn-sm"
                    >
                      View
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Pagination
        page={page.meta.current_page}
        lastPage={page.meta.last_page}
        onPage={(p) => void load({ ...filters, page: p })}
      />
      {page.data.length === 0 && filters.status === "" && !filters.classId && (
        <p className="mt-4 text-sm">
          <InlineHint />
        </p>
      )}
    </div>
  );
}

function InlineHint() {
  return (
    <span className="text-slate-500">
      Set each class&apos;s fees under{" "}
      <Link href="/admin/fees" className="link">
        Fee setup
      </Link>
      , then generate a month&apos;s invoices here.
    </span>
  );
}

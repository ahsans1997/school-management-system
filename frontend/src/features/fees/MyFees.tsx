"use client";

import { Printer } from "lucide-react";
import { useEffect, useState } from "react";
import { PageLoader } from "@/components/Loading";
import { errorMessage } from "@/lib/errors";
import { formatDay, formatMoney, formatPeriod } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as feeService from "@/services/feeService";
import { INVOICE_STATUSES, type MyFees as MyFeesData } from "@/types/fee";
import { InvoiceSheet } from "./InvoiceSheet";
import { INVOICE_BADGE } from "./InvoicesPage";

/** Student and parent portals: their own invoices and what's still due. */
export function MyFees() {
  const [data, setData] = useState<MyFeesData | null>(null);
  const [open, setOpen] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    feeService
      .getMyFees()
      .then((d) => {
        if (active) setData(d);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  if (!data) {
    return error ? (
      <div className="mx-auto max-w-4xl p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  const parent = data.type === "parent";
  const shown = open
    ? data.students.flatMap((s) => s.invoices).find((i) => i.id === open)
    : undefined;

  if (shown) {
    return (
      <div className="mx-auto max-w-4xl p-6 print:p-0">
        <div className="mb-4 flex items-center gap-3 print:hidden">
          <button
            type="button"
            onClick={() => setOpen(null)}
            className="text-sm text-slate-500 hover:underline"
          >
            ← All invoices
          </button>
          <button
            type="button"
            onClick={() => window.print()}
            className="btn btn-secondary btn-sm ml-auto"
          >
            <Printer className="h-4 w-4" /> Print
          </button>
        </div>
        <div className="overflow-hidden rounded-xl shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 print:rounded-none print:shadow-none print:ring-0">
          <InvoiceSheet invoice={shown} currency={data.currency} />
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Fees</h1>
      <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Monthly invoices and what&apos;s still due. Pay at the school office.
      </p>

      {data.students.length === 0 ? (
        <p className="text-sm text-slate-500">
          Your login isn&apos;t linked to a {parent ? "guardian" : "student"}{" "}
          record yet.
        </p>
      ) : (
        <div className="space-y-8">
          {data.students.map((s) => (
            <section key={s.student.id}>
              <div className="mb-3 flex flex-wrap items-baseline justify-between gap-3">
                {parent && (
                  <h2 className="text-lg font-semibold">{s.student.name}</h2>
                )}
                <p className="text-sm">
                  <span className="text-slate-500">Due now </span>
                  <strong
                    className={`text-lg tabular-nums ${Number(s.due) > 0 ? "text-amber-700 dark:text-amber-400" : "text-emerald-700 dark:text-emerald-400"}`}
                  >
                    {formatMoney(s.due, data.currency)}
                  </strong>
                </p>
              </div>
              {s.invoices.length === 0 ? (
                <p className="text-sm text-slate-500">No invoices yet.</p>
              ) : (
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr className="table-head">
                        <th className="py-2 pr-3 font-medium">Month</th>
                        <th className="py-2 pr-3 font-medium">Due by</th>
                        <th className="py-2 pr-3 text-right font-medium">
                          Total
                        </th>
                        <th className="py-2 pr-3 text-right font-medium">
                          Due
                        </th>
                        <th className="py-2 pr-3 font-medium">Status</th>
                        <th className="py-2 font-medium">
                          <span className="sr-only">Open</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {s.invoices.map((i) => (
                        <tr key={i.id} className="table-row">
                          <td className="py-2 pr-3 font-medium whitespace-nowrap">
                            {formatPeriod(i.period)}
                          </td>
                          <td className="py-2 pr-3 whitespace-nowrap">
                            {formatDay(i.due_date)}
                          </td>
                          <td className="py-2 pr-3 text-right tabular-nums">
                            {formatMoney(i.total, data.currency)}
                          </td>
                          <td className="py-2 pr-3 text-right font-semibold tabular-nums">
                            {formatMoney(i.due, data.currency)}
                          </td>
                          <td className="py-2 pr-3">
                            <span className={INVOICE_BADGE[i.status]}>
                              {labelOf(INVOICE_STATUSES, i.status)}
                            </span>
                          </td>
                          <td className="py-2 text-right">
                            <button
                              type="button"
                              onClick={() => setOpen(i.id)}
                              className="btn btn-secondary btn-sm"
                            >
                              View
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          ))}
        </div>
      )}
    </div>
  );
}

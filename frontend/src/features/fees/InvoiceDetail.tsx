"use client";

import { Printer } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { PageLoader } from "@/components/Loading";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatDate } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as feeService from "@/services/feeService";
import { INVOICE_STATUSES, type InvoiceDetail as Detail } from "@/types/fee";
import { InvoiceSheet } from "./InvoiceSheet";
import { INVOICE_BADGE } from "./InvoicesPage";

/** One invoice: printable, and voidable while nothing is paid against it. */
export function InvoiceDetail({ invoiceId }: { invoiceId: number }) {
  const { can } = useAuth();
  const [detail, setDetail] = useState<Detail | null>(null);
  const [voiding, setVoiding] = useState(false);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    feeService
      .getInvoice(invoiceId)
      .then((d) => {
        if (active) setDetail(d);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [invoiceId]);

  async function submitVoid() {
    setBusy(true);
    setError(null);
    try {
      setDetail(await feeService.voidInvoice(invoiceId, reason.trim()));
      setVoiding(false);
      setNotice("Invoice voided. Its month can be invoiced again.");
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  if (!detail) {
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

  const invoice = detail.data;
  const canVoid =
    can("fee.create") &&
    invoice.status !== "void" &&
    Number(invoice.paid) === 0;

  return (
    <div className="mx-auto max-w-4xl p-6 print:p-0">
      <div className="mb-4 flex flex-wrap items-center gap-3 print:hidden">
        <Link
          href="/admin/fees/invoices"
          className="text-sm text-slate-500 hover:underline"
        >
          ← Invoices
        </Link>
        <span className={INVOICE_BADGE[invoice.status]}>
          {labelOf(INVOICE_STATUSES, invoice.status)}
        </span>
        <div className="ml-auto flex gap-2">
          {canVoid && !voiding && (
            <button
              type="button"
              onClick={() => setVoiding(true)}
              className="btn btn-danger btn-sm"
            >
              Void
            </button>
          )}
          <button
            type="button"
            onClick={() => window.print()}
            className="btn btn-secondary btn-sm"
          >
            <Printer className="h-4 w-4" /> Print
          </button>
        </div>
      </div>

      {error && (
        <p role="alert" className="alert alert-error mb-4 print:hidden">
          {error}
        </p>
      )}
      {notice && (
        <p className="alert alert-success mb-4 print:hidden">{notice}</p>
      )}

      {voiding && (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            void submitVoid();
          }}
          className="card mb-4 space-y-2 p-4 print:hidden"
        >
          <label className="block text-sm">
            <span className="mb-1 block font-medium">
              Why is this invoice being voided?
            </span>
            <input
              required
              minLength={3}
              maxLength={255}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="e.g. Billed to the wrong class"
              className="input w-full"
            />
          </label>
          <p className="text-xs text-slate-500">
            The invoice stays on record as void, and the student can be invoiced
            for {invoice.period} again.
          </p>
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={busy}
              className="btn btn-danger btn-sm"
            >
              {busy ? "Voiding…" : "Void invoice"}
            </button>
            <button
              type="button"
              onClick={() => setVoiding(false)}
              className="btn btn-secondary btn-sm"
            >
              Cancel
            </button>
          </div>
        </form>
      )}

      <div className="overflow-hidden rounded-xl shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 print:rounded-none print:shadow-none print:ring-0">
        <InvoiceSheet
          invoice={invoice}
          school={detail.school}
          currency={detail.currency}
        />
      </div>

      <p className="mt-3 text-xs text-slate-500 print:hidden">
        Issued {formatDate(invoice.issued_at)}
        {invoice.issued_by ? ` by ${invoice.issued_by}` : ""}
        {invoice.voided_at &&
          ` · Voided ${formatDate(invoice.voided_at)}${invoice.voided_by ? ` by ${invoice.voided_by}` : ""}`}
      </p>
    </div>
  );
}

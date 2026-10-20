import { formatDay, formatMoney, formatPeriod } from "@/lib/format";
import { labelOf } from "@/lib/options";
import { INVOICE_STATUSES, type FeeInvoice } from "@/types/fee";
import type { Letterhead } from "@/types/studentTools";

/** An invoice as printed on A4 (always light, like paper). */
export function InvoiceSheet({
  invoice,
  school,
  currency,
}: {
  invoice: FeeInvoice;
  school?: Letterhead;
  currency: string;
}) {
  const money = (v: string) => formatMoney(v, currency);
  return (
    <article className="mx-auto max-w-[190mm] bg-white p-[10mm] text-[10pt] leading-snug text-slate-900 print:p-0">
      {school && (
        <header className="mb-[5mm] border-b-2 border-slate-900 pb-[3mm] text-center">
          {school.logo_url && (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={school.logo_url}
              alt=""
              onError={(e) => {
                e.currentTarget.style.display = "none";
              }}
              className="mx-auto mb-[2mm] h-[14mm] w-auto object-contain"
            />
          )}
          <h2 className="text-[15pt] font-bold">{school.name}</h2>
          {school.address && <p className="text-[9pt]">{school.address}</p>}
        </header>
      )}

      <div className="mb-[5mm] flex flex-wrap items-start justify-between gap-[4mm]">
        <div>
          <h3 className="text-[13pt] font-bold tracking-wide uppercase">
            Fee invoice
          </h3>
          <p className="font-mono text-[10pt]">{invoice.invoice_no}</p>
        </div>
        <dl className="grid grid-cols-[auto_auto] gap-x-[4mm] text-[9.5pt]">
          <dt className="text-slate-600">Month</dt>
          <dd className="font-medium">{formatPeriod(invoice.period)}</dd>
          <dt className="text-slate-600">Due by</dt>
          <dd className="font-medium">{formatDay(invoice.due_date)}</dd>
          <dt className="text-slate-600">Status</dt>
          <dd className="font-medium">
            {labelOf(INVOICE_STATUSES, invoice.status)}
          </dd>
        </dl>
      </div>

      {invoice.student && (
        <p className="mb-[4mm]">
          <strong>{invoice.student.name}</strong> · Student ID{" "}
          {invoice.student.student_id}
          {invoice.class && ` · ${invoice.class.name}`}
          {invoice.section && ` ${invoice.section.name}`}
          {invoice.roll !== undefined && ` · Roll ${invoice.roll}`}
        </p>
      )}

      <table className="mb-[4mm] w-full border-collapse text-[9.5pt]">
        <thead>
          <tr className="bg-slate-100">
            <th className="border border-slate-300 px-2 py-1 text-left">
              Description
            </th>
            <th className="border border-slate-300 px-2 py-1 text-right">
              Amount
            </th>
          </tr>
        </thead>
        <tbody>
          {invoice.items?.map((item) => (
            <tr key={item.id}>
              <td className="border border-slate-300 px-2 py-1">
                {item.description}
              </td>
              <td className="border border-slate-300 px-2 py-1 text-right tabular-nums">
                {money(item.amount)}
              </td>
            </tr>
          ))}
        </tbody>
        <tfoot className="tabular-nums">
          <tr>
            <td className="border border-slate-300 px-2 py-1 text-right font-semibold">
              Total
            </td>
            <td className="border border-slate-300 px-2 py-1 text-right font-semibold">
              {money(invoice.total)}
            </td>
          </tr>
          <tr>
            <td className="border border-slate-300 px-2 py-1 text-right">
              Paid
            </td>
            <td className="border border-slate-300 px-2 py-1 text-right">
              {money(invoice.paid)}
            </td>
          </tr>
          <tr>
            <td className="border border-slate-300 px-2 py-1 text-right font-bold">
              Due
            </td>
            <td className="border border-slate-300 px-2 py-1 text-right font-bold">
              {invoice.status === "void" ? "—" : money(invoice.due)}
            </td>
          </tr>
        </tfoot>
      </table>

      {invoice.status === "void" && (
        <p className="font-semibold text-red-700">
          VOID{invoice.void_reason ? `: ${invoice.void_reason}` : ""}
        </p>
      )}
    </article>
  );
}

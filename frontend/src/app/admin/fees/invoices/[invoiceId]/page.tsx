"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { InvoiceDetail } from "@/features/fees/InvoiceDetail";

export default function InvoiceRoute({
  params,
}: {
  params: Promise<{ invoiceId: string }>;
}) {
  const { invoiceId } = use(params);

  return (
    <RequireAuth permission="fee.view">
      <InvoiceDetail key={invoiceId} invoiceId={Number(invoiceId)} />
    </RequireAuth>
  );
}

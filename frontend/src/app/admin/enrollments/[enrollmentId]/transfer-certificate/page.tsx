"use client";

import { use } from "react";
import { RequireAuth } from "@/components/RequireAuth";
import { TransferCertificatePage } from "@/features/enrollments/TransferCertificatePage";

export default function TransferCertificateRoute({
  params,
}: {
  params: Promise<{ enrollmentId: string }>;
}) {
  const { enrollmentId } = use(params);

  return (
    <RequireAuth permission="enrollment.view">
      <TransferCertificatePage enrollmentId={Number(enrollmentId)} />
    </RequireAuth>
  );
}

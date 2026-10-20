"use client";

import { Printer } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { PageLoader } from "@/components/Loading";
import { errorMessage } from "@/lib/errors";
import { labelOf } from "@/lib/options";
import * as resultService from "@/services/resultService";
import { RESULT_STATUSES, type ReportCard } from "@/types/result";
import { ReportCardSheet } from "./ReportCardSheet";
import { RESULT_BADGE } from "./ResultsPage";

/** One student's report card, ready to print. */
export function ReportCardPage({
  resultId,
  basePath,
}: {
  resultId: number;
  basePath: string;
}) {
  const [card, setCard] = useState<ReportCard | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    resultService
      .getReportCard(resultId)
      .then((c) => {
        if (active) setCard(c);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [resultId]);

  if (!card) {
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

  return (
    <div className="mx-auto max-w-4xl p-6 print:p-0">
      <div className="mb-4 flex flex-wrap items-center gap-3 print:hidden">
        <Link
          href={`${basePath}/class?exam=${card.exam.id}&class=${card.class.id}`}
          className="text-sm text-slate-500 hover:underline"
        >
          ← {card.class.name} results
        </Link>
        <span className={RESULT_BADGE[card.status]}>
          {labelOf(RESULT_STATUSES, card.status)}
        </span>
        <button
          type="button"
          onClick={() => window.print()}
          className="btn btn-secondary btn-sm ml-auto"
        >
          <Printer className="h-4 w-4" /> Print
        </button>
      </div>
      <div className="overflow-hidden rounded-xl shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 print:rounded-none print:shadow-none print:ring-0">
        <ReportCardSheet card={card} />
      </div>
    </div>
  );
}

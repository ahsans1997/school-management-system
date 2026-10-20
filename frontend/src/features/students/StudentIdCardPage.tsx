"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { errorMessage } from "@/lib/errors";
import * as studentToolsService from "@/services/studentToolsService";
import type { IdCardSheet as Sheet } from "@/types/studentTools";
import { IdCardSheet } from "./IdCardSheet";
import { InlineLoader } from "@/components/Loading";

/** One student's ID card, ready to print. */
export function StudentIdCardPage({ studentId }: { studentId: number }) {
  const [sheet, setSheet] = useState<Sheet | null>(null);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    studentToolsService
      .getIdCard(studentId)
      .then((s) => {
        if (active) setSheet(s);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [studentId]);

  return (
    <div className="mx-auto max-w-5xl p-6 print:p-0">
      <div className="mb-6 print:hidden">
        <Link
          href={`/admin/students/${studentId}`}
          className="text-sm text-slate-500 hover:underline"
        >
          ← Student profile
        </Link>
        <div className="mt-1 flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-2xl font-semibold">ID card</h1>
          {sheet && (
            <button
              type="button"
              onClick={() => window.print()}
              className="btn btn-primary"
            >
              Print
            </button>
          )}
        </div>
      </div>

      {error ? (
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      ) : !sheet ? (
        <InlineLoader />
      ) : (
        <IdCardSheet sheet={sheet} />
      )}
    </div>
  );
}

"use client";

import Link from "next/link";
import { useEffect, useState, type FormEvent } from "react";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatDay } from "@/lib/format";
import { ApiRequestError } from "@/services/apiClient";
import * as studentToolsService from "@/services/studentToolsService";
import type { TransferCertificate } from "@/types/studentTools";

const inputClass = "input input-sm";

/** Words that follow the student's recorded gender. */
function wordsFor(gender: string) {
  if (gender === "male") return { child: "son", they: "He", them: "him" };
  if (gender === "female")
    return { child: "daughter", they: "She", them: "her" };
  return { child: "child", they: "They", them: "them" };
}

/** The certificate as printed on A4. Always light, like paper. */
function Certificate({ tc }: { tc: TransferCertificate }) {
  const w = wordsFor(tc.student.gender);
  const parents = [tc.student.father_name, tc.student.mother_name]
    .filter(Boolean)
    .join(" and ");

  return (
    <article className="mx-auto max-w-[190mm] bg-white p-[12mm] text-[11pt] leading-relaxed text-slate-900 print:p-0">
      <header className="mb-[8mm] border-b-2 border-slate-900 pb-[4mm] text-center">
        {tc.school.logo_url && (
          // Logo from the API host; plain img prints reliably. A logo that
          // fails to load is hidden rather than printed as a broken image.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={tc.school.logo_url}
            alt=""
            onError={(e) => {
              e.currentTarget.style.display = "none";
            }}
            className="mx-auto mb-[2mm] h-[18mm] w-auto object-contain"
          />
        )}
        <h2 className="text-[18pt] font-bold">{tc.school.name}</h2>
        {tc.school.address && (
          <p className="text-[10pt]">{tc.school.address}</p>
        )}
        <p className="text-[9pt] text-slate-600">
          {[
            tc.school.eiin && `EIIN ${tc.school.eiin}`,
            tc.school.phone,
            tc.school.email,
          ]
            .filter(Boolean)
            .join(" · ")}
        </p>
      </header>

      <div className="mb-[6mm] flex justify-between text-[10pt]">
        <span>
          Serial no. <strong className="font-mono">{tc.serial_no}</strong>
        </span>
        <span>Date: {formatDay(tc.issued_on)}</span>
      </div>

      <h3 className="mb-[8mm] text-center text-[15pt] font-bold tracking-widest uppercase underline underline-offset-4">
        Transfer certificate
      </h3>

      <p className="mb-[4mm] text-justify">
        This is to certify that <strong>{tc.student.name}</strong>
        {parents && (
          <>
            , {w.child} of <strong>{parents}</strong>
          </>
        )}
        , born on <strong>{formatDay(tc.student.dob)}</strong>, was a student of
        this school (student ID {tc.student.student_id}, admission no.{" "}
        {tc.student.admission_no}, admitted on{" "}
        {formatDay(tc.student.admission_date)}).
      </p>
      <p className="mb-[4mm] text-justify">
        {w.they} studied in <strong>{tc.enrollment.class}</strong>, section{" "}
        <strong>{tc.enrollment.section}</strong>, roll {tc.enrollment.roll}, in
        the {tc.enrollment.academic_year} academic year, and left the school on{" "}
        <strong>{formatDay(tc.enrollment.left_on)}</strong>
        {tc.enrollment.reason && <> ({tc.enrollment.reason})</>}.
      </p>
      <p className="mb-[4mm]">
        Conduct and character: <strong>{tc.conduct ?? "Good"}</strong>.
      </p>
      <p>We wish {w.them} every success.</p>

      <footer className="mt-[28mm] flex justify-between text-[10pt]">
        <div className="w-[55mm] border-t border-slate-900 pt-[1mm] text-center">
          Class teacher
        </div>
        <div className="w-[55mm] border-t border-slate-900 pt-[1mm] text-center">
          Principal
          {tc.school.principal_name && (
            <span className="block text-[9pt]">{tc.school.principal_name}</span>
          )}
        </div>
      </footer>
    </article>
  );
}

/**
 * The transfer certificate for a transferred enrollment: issue it once
 * (serial number and date stay fixed), then print or reprint.
 */
export function TransferCertificatePage({
  enrollmentId,
}: {
  enrollmentId: number;
}) {
  const { can } = useAuth();
  const canIssue = can("enrollment.edit");
  const [tc, setTc] = useState<TransferCertificate | null>(null);
  const [notIssued, setNotIssued] = useState(false);
  const [conduct, setConduct] = useState("Good");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    studentToolsService
      .getTransferCertificate(enrollmentId)
      .then((c) => {
        if (!active) return;
        setTc(c);
        setConduct(c.conduct ?? "Good");
      })
      .catch((err) => {
        if (!active) return;
        if (err instanceof ApiRequestError && err.error.status === 404)
          setNotIssued(true);
        else setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [enrollmentId]);

  async function issue(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      const c = await studentToolsService.issueTransferCertificate(
        enrollmentId,
        conduct.trim() || null,
      );
      setNotice(tc ? "Conduct updated." : `Certificate ${c.serial_no} issued.`);
      setTc(c);
      setNotIssued(false);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-4xl p-6 print:max-w-none print:p-0">
      <div className="mb-6 print:hidden">
        {tc ? (
          <Link
            href={`/admin/students/${tc.student.id}`}
            className="text-sm text-slate-500 hover:underline"
          >
            ← {tc.student.name}
          </Link>
        ) : (
          <Link
            href="/admin/enrollments"
            className="text-sm text-slate-500 hover:underline"
          >
            ← Enrollments
          </Link>
        )}
        <div className="mt-1 flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-2xl font-semibold">Transfer certificate</h1>
          {tc && (
            <button
              type="button"
              onClick={() => window.print()}
              className="btn btn-primary"
            >
              Print
            </button>
          )}
        </div>

        {error && (
          <p role="alert" className="alert alert-error mt-3">
            {error}
          </p>
        )}
        {notice && <p className="alert alert-success mt-3">{notice}</p>}

        {(tc || notIssued) && canIssue && (
          <form
            onSubmit={issue}
            className="mt-4 flex flex-wrap items-end gap-2"
          >
            <label className="text-sm">
              <span className="mb-1 block font-medium">
                Conduct and character
              </span>
              <input
                maxLength={100}
                value={conduct}
                onChange={(e) => setConduct(e.target.value)}
                className={`w-56 ${inputClass}`}
              />
            </label>
            <button type="submit" disabled={busy} className="btn btn-secondary">
              {busy ? "Saving…" : tc ? "Update conduct" : "Issue certificate"}
            </button>
            {!tc && (
              <span className="text-xs text-slate-500">
                Gets the next serial number; reprints show the same one.
              </span>
            )}
          </form>
        )}
        {notIssued && !canIssue && (
          <p className="mt-3 text-sm text-slate-500">
            No transfer certificate has been issued for this enrollment yet.
          </p>
        )}
      </div>

      {tc && <Certificate tc={tc} />}
    </div>
  );
}

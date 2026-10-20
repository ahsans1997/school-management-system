import { formatDay } from "@/lib/format";
import type { ReportCard } from "@/types/result";

const n = (v: number | null) => (v === null ? "—" : String(v));

/**
 * A student's report card / mark sheet as printed on A4 — always light,
 * like paper. CQ / MCQ / practical columns appear only when a paper is split.
 */
export function ReportCardSheet({ card }: { card: ReportCard }) {
  const split = {
    cq: card.subjects.some((s) => s.cq !== null),
    mcq: card.subjects.some((s) => s.mcq !== null),
    practical: card.subjects.some((s) => s.practical !== null),
  };
  const cell = "border border-slate-300 px-2 py-1";
  const info: [string, string][] = [
    ["Name", card.student.name],
    ["Student ID", card.student.student_id],
    ["Class", `${card.class.name} · Section ${card.section.name}`],
    ["Roll", String(card.roll)],
    ["Father", card.student.father_name ?? "—"],
    ["Mother", card.student.mother_name ?? "—"],
  ];

  return (
    <article className="mx-auto max-w-[190mm] bg-white p-[10mm] text-[10pt] leading-snug text-slate-900 print:p-0">
      <header className="mb-[5mm] border-b-2 border-slate-900 pb-[3mm] text-center">
        {card.school.logo_url && (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={card.school.logo_url}
            alt=""
            onError={(e) => {
              e.currentTarget.style.display = "none";
            }}
            className="mx-auto mb-[2mm] h-[15mm] w-auto object-contain"
          />
        )}
        <h2 className="text-[16pt] font-bold">{card.school.name}</h2>
        {card.school.address && (
          <p className="text-[9pt]">{card.school.address}</p>
        )}
        <h3 className="mt-[3mm] text-[12pt] font-bold tracking-wider uppercase">
          Report card · {card.exam.name} {card.exam.academic_year}
        </h3>
      </header>

      <dl className="mb-[4mm] grid grid-cols-2 gap-x-[8mm] gap-y-[1mm]">
        {info.map(([label, value]) => (
          <div key={label} className="flex gap-2">
            <dt className="w-[22mm] shrink-0 text-slate-600">{label}</dt>
            <dd className="font-medium">{value}</dd>
          </div>
        ))}
      </dl>

      <table className="mb-[4mm] w-full border-collapse text-[9.5pt]">
        <thead>
          <tr className="bg-slate-100">
            <th className={`${cell} text-left`}>Subject</th>
            <th className={cell}>Full</th>
            {split.cq && <th className={cell}>CQ</th>}
            {split.mcq && <th className={cell}>MCQ</th>}
            {split.practical && <th className={cell}>Prac.</th>}
            <th className={cell}>Obtained</th>
            <th className={cell}>Grade</th>
            <th className={cell}>Point</th>
          </tr>
        </thead>
        <tbody>
          {card.subjects.map((s) => (
            <tr key={s.exam_subject_id} className="text-center tabular-nums">
              <td className={`${cell} text-left`}>
                {s.subject}
                {s.code && (
                  <span className="text-[8pt] text-slate-500"> ({s.code})</span>
                )}
              </td>
              <td className={cell}>{s.full_marks}</td>
              {split.cq && <td className={cell}>{n(s.cq)}</td>}
              {split.mcq && <td className={cell}>{n(s.mcq)}</td>}
              {split.practical && <td className={cell}>{n(s.practical)}</td>}
              <td className={`${cell} font-semibold`}>
                {s.absent ? "Absent" : s.obtained}
              </td>
              <td
                className={`${cell} font-semibold ${s.passed ? "" : "text-red-700"}`}
              >
                {s.grade}
              </td>
              <td className={cell}>{s.grade_point.toFixed(2)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="mb-[8mm] grid grid-cols-3 gap-[3mm] text-center">
        {[
          ["Total", `${card.total_marks} / ${card.full_marks}`],
          ["GPA", card.gpa.toFixed(2)],
          ["Grade", card.grade],
          [
            "Result",
            card.passed ? "Passed" : `Failed (${card.failed_subjects})`,
          ],
          [
            "Position in section",
            card.position
              ? `${card.position} of ${card.section_students}`
              : "—",
          ],
          [
            "Position in class",
            card.class_position
              ? `${card.class_position} of ${card.class_students}`
              : "—",
          ],
        ].map(([label, value]) => (
          <div key={label} className="rounded border border-slate-300 p-[2mm]">
            <p className="text-[8pt] text-slate-600 uppercase">{label}</p>
            <p className="text-[12pt] font-bold">{value}</p>
          </div>
        ))}
      </div>

      <footer className="flex items-end justify-between pt-[10mm] text-[9pt]">
        <span className="text-slate-600">
          {card.published_at
            ? `Published ${formatDay(card.published_at.slice(0, 10))}`
            : "Not published"}
        </span>
        <span className="border-t border-slate-900 px-[6mm] pt-[1mm]">
          Class teacher
        </span>
        <span className="border-t border-slate-900 px-[6mm] pt-[1mm]">
          {card.school.principal_name ?? "Principal"}
        </span>
      </footer>
    </article>
  );
}

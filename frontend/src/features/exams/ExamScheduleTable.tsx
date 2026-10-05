import type { ExamPaper } from "@/types/exam";

/** "Tue 10 Nov 2026" from YYYY-MM-DD, built from local parts (not UTC). */
export function formatPaperDay(day: string): string {
  const [y, m, d] = day.split("-").map(Number);
  return new Date(y, m - 1, d).toLocaleDateString("en-GB", {
    weekday: "short",
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

/** "100" or "32.5", never "100.00". */
export function formatMarks(value: number | null): string {
  return value === null ? "—" : String(Number(value.toFixed(2)));
}

/** "CQ 70 · MCQ 30" for a paper with a split, or "". */
export function splitOf(p: ExamPaper): string {
  return [
    p.cq_marks !== null && `CQ ${formatMarks(p.cq_marks)}`,
    p.mcq_marks !== null && `MCQ ${formatMarks(p.mcq_marks)}`,
    p.practical_marks !== null && `Practical ${formatMarks(p.practical_marks)}`,
  ]
    .filter(Boolean)
    .join(" · ");
}

/**
 * An exam timetable, papers already in date order from the API (undated
 * papers last). `showClass` adds the class column for whole-exam views.
 */
export function ExamScheduleTable({
  papers,
  showClass = false,
}: {
  papers: ExamPaper[];
  showClass?: boolean;
}) {
  if (papers.length === 0) {
    return <p className="text-sm text-gray-500">No papers yet.</p>;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-gray-200 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <th className="py-2 pr-3 font-medium">Date</th>
            <th className="py-2 pr-3 font-medium">Time</th>
            {showClass && <th className="py-2 pr-3 font-medium">Class</th>}
            <th className="py-2 pr-3 font-medium">Subject</th>
            <th className="py-2 pr-3 font-medium">Full / pass</th>
          </tr>
        </thead>
        <tbody>
          {papers.map((p, i) => {
            const repeatDay = i > 0 && papers[i - 1].exam_date === p.exam_date;
            const split = splitOf(p);
            return (
              <tr
                key={p.id}
                className="border-b border-gray-100 align-top dark:border-gray-900"
              >
                <td className="py-2 pr-3 whitespace-nowrap">
                  {repeatDay ? (
                    <span className="sr-only">
                      {p.exam_date
                        ? formatPaperDay(p.exam_date)
                        : "Not scheduled"}
                    </span>
                  ) : p.exam_date ? (
                    formatPaperDay(p.exam_date)
                  ) : (
                    <span className="text-gray-500">Date to be set</span>
                  )}
                </td>
                <td className="py-2 pr-3 whitespace-nowrap tabular-nums">
                  {p.start_time ? `${p.start_time}–${p.end_time}` : "—"}
                </td>
                {showClass && (
                  <td className="py-2 pr-3 whitespace-nowrap">
                    {p.class?.name}
                  </td>
                )}
                <td className="py-2 pr-3">
                  <span className="font-medium">{p.subject?.name}</span>
                  {p.section && (
                    <span className="ml-1 text-xs text-gray-500">
                      Section {p.section.name} only
                    </span>
                  )}
                  {split && (
                    <span className="block text-xs text-gray-500">{split}</span>
                  )}
                </td>
                <td className="py-2 pr-3 whitespace-nowrap tabular-nums">
                  {formatMarks(p.full_marks)} / {formatMarks(p.pass_marks)}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

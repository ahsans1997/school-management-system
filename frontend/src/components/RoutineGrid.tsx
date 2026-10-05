import { WEEKDAYS, type RoutineSlot } from "@/types/routine";

/**
 * Read-only week grid: one column per day that has periods, one row per
 * period number. `perspective` decides what a cell names: a section view
 * shows the teacher, a teacher view shows the section, a room view both.
 */
export function RoutineGrid({
  slots,
  perspective,
}: {
  slots: RoutineSlot[];
  perspective: "section" | "teacher" | "room";
}) {
  if (slots.length === 0) {
    return <p className="text-sm text-gray-500">No periods yet.</p>;
  }

  const days = WEEKDAYS.filter((d) => slots.some((s) => s.day === d.value));
  const periods = [...new Set(slots.map((s) => s.period_no))].sort(
    (a, b) => a - b,
  );
  const at = (day: string, period: number) =>
    slots.filter((s) => s.day === day && s.period_no === period);

  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-max border-collapse text-xs">
        <thead>
          <tr>
            <th className="border border-gray-200 bg-gray-50 px-2 py-1.5 text-left font-medium text-gray-500 dark:border-gray-800 dark:bg-gray-900">
              Period
            </th>
            {days.map((d) => (
              <th
                key={d.value}
                className="border border-gray-200 bg-gray-50 px-2 py-1.5 text-left font-medium dark:border-gray-800 dark:bg-gray-900"
              >
                {d.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {periods.map((p) => (
            <tr key={p}>
              <th className="border border-gray-200 px-2 py-1.5 text-left font-medium text-gray-500 tabular-nums dark:border-gray-800">
                {p}
              </th>
              {days.map((d) => (
                <td
                  key={d.value}
                  className="border border-gray-200 px-2 py-1.5 align-top dark:border-gray-800"
                >
                  {at(d.value, p).map((s) => (
                    <div key={s.id} className="mb-1 last:mb-0">
                      <div className="text-gray-500 tabular-nums">
                        {s.start_time}–{s.end_time}
                      </div>
                      {s.label ? (
                        <div className="italic">{s.label}</div>
                      ) : (
                        <>
                          <div className="font-medium">{s.subject?.name}</div>
                          <div className="text-gray-600 dark:text-gray-400">
                            {perspective === "section"
                              ? s.teacher?.name
                              : `${s.class.name} ${s.section.name}${perspective === "room" && s.teacher ? ` · ${s.teacher.name}` : ""}`}
                          </div>
                        </>
                      )}
                      {s.room && perspective !== "room" && (
                        <div className="text-gray-500">Room {s.room}</div>
                      )}
                    </div>
                  ))}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

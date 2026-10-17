import { formatDay } from "@/lib/format";
import type {
  IdCard,
  IdCardSheet as Sheet,
  Letterhead,
} from "@/types/studentTools";

/**
 * One ID card at the standard card size (85.6 × 54 mm, like a bank card).
 * Always light, so the screen shows what prints.
 */
function Card({
  card,
  school,
  year,
  validUntil,
}: {
  card: IdCard;
  school: Letterhead;
  year: string;
  validUntil: string;
}) {
  return (
    <article className="flex h-[54mm] w-[85.6mm] break-inside-avoid flex-col overflow-hidden rounded-[3mm] border border-gray-400 bg-white text-gray-900">
      <header className="flex items-center gap-[2mm] bg-gray-900 px-[3mm] py-[1.5mm] text-white">
        {school.logo_url && (
          // Logo from the API host; plain img prints reliably. A logo that
          // fails to load is hidden rather than printed as a broken image.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={school.logo_url}
            alt=""
            onError={(e) => {
              e.currentTarget.style.display = "none";
            }}
            className="h-[7mm] w-[7mm] shrink-0 rounded-sm bg-white object-contain"
          />
        )}
        <div className="min-w-0 leading-tight">
          <p className="truncate text-[8pt] font-semibold">{school.name}</p>
          <p className="text-[6pt] tracking-wide uppercase opacity-80">
            Student ID card · {year}
          </p>
        </div>
      </header>

      <div className="flex min-h-0 flex-1 gap-[2.5mm] px-[3mm] py-[2mm]">
        {card.photo_url ? (
          // Signed private photo link; next/image can't load it.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={card.photo_url}
            alt=""
            className="h-[26mm] w-[21mm] shrink-0 rounded-sm border border-gray-300 object-cover"
          />
        ) : (
          <div className="flex h-[26mm] w-[21mm] shrink-0 items-center justify-center rounded-sm border border-dashed border-gray-400 text-[6pt] text-gray-500">
            No photo
          </div>
        )}
        <dl className="min-w-0 text-[7pt] leading-snug">
          <dt className="sr-only">Name</dt>
          <dd className="truncate text-[9pt] font-semibold">{card.name}</dd>
          <div className="mt-[0.5mm] flex gap-[1mm]">
            <dt className="text-gray-500">ID</dt>
            <dd className="font-mono">{card.id_no}</dd>
          </div>
          <div className="flex gap-[1mm]">
            <dt className="text-gray-500">Class</dt>
            <dd>
              {card.class} · {card.section} · Roll {card.roll}
            </dd>
          </div>
          <div className="flex gap-[1mm]">
            <dt className="text-gray-500">Born</dt>
            <dd>
              {formatDay(card.dob)}
              {card.blood_group && ` · ${card.blood_group}`}
            </dd>
          </div>
          {card.contact_phone && (
            <div className="flex gap-[1mm]">
              <dt className="text-gray-500">Contact</dt>
              <dd>{card.contact_phone}</dd>
            </div>
          )}
        </dl>
      </div>

      <footer className="flex items-end justify-between border-t border-gray-200 px-[3mm] py-[1mm] text-[6pt] text-gray-600">
        <span>Valid until {formatDay(validUntil)}</span>
        <span className="border-t border-gray-500 px-[2mm] pt-[0.3mm]">
          Principal
        </span>
      </footer>
    </article>
  );
}

/** A printable sheet of ID cards (two per row on A4). */
export function IdCardSheet({ sheet }: { sheet: Sheet }) {
  if (sheet.cards.length === 0) {
    return (
      <p className="text-sm text-gray-500">
        No students are actively enrolled here for {sheet.academic_year.name}.
      </p>
    );
  }

  return (
    <div className="flex flex-wrap gap-[4mm]">
      {sheet.cards.map((card) => (
        <Card
          key={card.student_id}
          card={card}
          school={sheet.school}
          year={sheet.academic_year.name}
          validUntil={sheet.academic_year.valid_until}
        />
      ))}
    </div>
  );
}

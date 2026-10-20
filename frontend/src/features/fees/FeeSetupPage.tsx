"use client";

import { useCallback, useEffect, useState } from "react";
import { InlineLoader, PageLoader } from "@/components/Loading";
import { useAuth } from "@/features/auth/AuthContext";
import { errorMessage } from "@/lib/errors";
import { formatMoney } from "@/lib/format";
import { labelOf } from "@/lib/options";
import * as feeService from "@/services/feeService";
import {
  FEE_CATEGORIES,
  FEE_FREQUENCIES,
  type FeeCategory,
  type FeeFrequency,
  type FeeOptions,
  type FeeStructure,
  type FeeType,
} from "@/types/fee";

const inputClass = "input input-sm";

/** Whole paisa from a "1500.50" string — only for showing a sum. */
const paisa = (amount: string) => Math.round(Number(amount) * 100);

/**
 * Fee setup: the fee-type catalogue, and what each class pays per fee type
 * in each academic year. Writes need fee.create.
 */
export function FeeSetupPage() {
  const [tab, setTab] = useState<"structures" | "types">("structures");
  const [options, setOptions] = useState<FeeOptions | null>(null);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(() => {
    feeService
      .getOptions()
      .then(setOptions)
      .catch((err) => setError(errorMessage(err)));
  }, []);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    feeService
      .getOptions()
      .then((o) => {
        if (active) setOptions(o);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  if (!options) {
    return error ? (
      <div className="p-6">
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      </div>
    ) : (
      <PageLoader />
    );
  }

  const tabClass = (on: boolean) =>
    `rounded-md px-3 py-1 text-sm ${on ? "bg-brand-600 text-white" : "border border-slate-300 dark:border-slate-700"}`;

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-1 text-2xl font-semibold">Fee setup</h1>
      <p className="mb-5 text-sm text-slate-500 dark:text-slate-400">
        Set what each class pays per fee type for a year. Invoices copy these
        amounts when they&apos;re generated, so changing one later doesn&apos;t
        alter invoices already issued.
      </p>
      <div role="tablist" className="mb-5 flex gap-2">
        <button
          type="button"
          role="tab"
          aria-selected={tab === "structures"}
          onClick={() => setTab("structures")}
          className={tabClass(tab === "structures")}
        >
          Class fees
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={tab === "types"}
          onClick={() => setTab("types")}
          className={tabClass(tab === "types")}
        >
          Fee types
        </button>
      </div>
      {tab === "structures" ? (
        <StructuresManager options={options} />
      ) : (
        <TypesManager onChange={reload} />
      )}
    </div>
  );
}

interface TypeDraft {
  name: string;
  code: string;
  category: FeeCategory;
  is_active: boolean;
}

const EMPTY_TYPE: TypeDraft = {
  name: "",
  code: "",
  category: "tuition",
  is_active: true,
};

function TypesManager({ onChange }: { onChange: () => void }) {
  const { can } = useAuth();
  const canEdit = can("fee.create");
  const [types, setTypes] = useState<FeeType[] | null>(null);
  const [draft, setDraft] = useState<TypeDraft>(EMPTY_TYPE);
  const [editing, setEditing] = useState<number | null>(null);
  const [edit, setEdit] = useState<TypeDraft>(EMPTY_TYPE);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // State set only in promise callbacks.
  useEffect(() => {
    let active = true;
    feeService
      .listFeeTypes()
      .then((p) => {
        if (active) setTypes(p.data);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, []);

  async function run(task: () => Promise<unknown>) {
    setBusy(true);
    setError(null);
    try {
      await task();
      setTypes((await feeService.listFeeTypes()).data);
      onChange();
      return true;
    } catch (err) {
      setError(errorMessage(err));
      return false;
    } finally {
      setBusy(false);
    }
  }

  const input = (d: TypeDraft) => ({
    name: d.name.trim(),
    code: d.code.trim() || null,
    category: d.category,
    is_active: d.is_active,
  });

  if (!types)
    return error ? (
      <p className="alert alert-error">{error}</p>
    ) : (
      <InlineLoader />
    );

  return (
    <div>
      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}
      {canEdit && (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            void run(() => feeService.createFeeType(input(draft))).then(
              (ok) => ok && setDraft(EMPTY_TYPE),
            );
          }}
          className="card mb-5 flex flex-wrap items-end gap-2 p-4"
        >
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Name</span>
            <input
              required
              maxLength={100}
              value={draft.name}
              onChange={(e) => setDraft({ ...draft, name: e.target.value })}
              placeholder="e.g. Tuition"
              className={`w-48 ${inputClass}`}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Code</span>
            <input
              maxLength={20}
              value={draft.code}
              onChange={(e) => setDraft({ ...draft, code: e.target.value })}
              placeholder="optional"
              className={`w-28 ${inputClass}`}
            />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-xs text-slate-500">Category</span>
            <select
              value={draft.category}
              onChange={(e) =>
                setDraft({ ...draft, category: e.target.value as FeeCategory })
              }
              className={inputClass}
            >
              {FEE_CATEGORIES.map((c) => (
                <option key={c.value} value={c.value}>
                  {c.label}
                </option>
              ))}
            </select>
          </label>
          <button type="submit" disabled={busy} className="btn btn-primary">
            Add fee type
          </button>
        </form>
      )}

      {types.length === 0 ? (
        <p className="text-sm text-slate-500">No fee types yet.</p>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr className="table-head">
                <th className="py-2 pr-3 font-medium">Fee type</th>
                <th className="py-2 pr-3 font-medium">Code</th>
                <th className="py-2 pr-3 font-medium">Category</th>
                <th className="py-2 pr-3 font-medium">Used by</th>
                <th className="py-2 pr-3 font-medium">Status</th>
                {canEdit && (
                  <th className="py-2 font-medium">
                    <span className="sr-only">Actions</span>
                  </th>
                )}
              </tr>
            </thead>
            <tbody>
              {types.map((t) =>
                editing === t.id ? (
                  <tr key={t.id} className="table-row">
                    <td className="py-2 pr-3">
                      <input
                        aria-label="Name"
                        value={edit.name}
                        onChange={(e) =>
                          setEdit({ ...edit, name: e.target.value })
                        }
                        className={`w-40 ${inputClass}`}
                      />
                    </td>
                    <td className="py-2 pr-3">
                      <input
                        aria-label="Code"
                        value={edit.code}
                        onChange={(e) =>
                          setEdit({ ...edit, code: e.target.value })
                        }
                        className={`w-24 ${inputClass}`}
                      />
                    </td>
                    <td className="py-2 pr-3">
                      <select
                        aria-label="Category"
                        value={edit.category}
                        onChange={(e) =>
                          setEdit({
                            ...edit,
                            category: e.target.value as FeeCategory,
                          })
                        }
                        className={inputClass}
                      >
                        {FEE_CATEGORIES.map((c) => (
                          <option key={c.value} value={c.value}>
                            {c.label}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="py-2 pr-3 text-slate-500">
                      {t.structures_count ?? 0}
                    </td>
                    <td className="py-2 pr-3">
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={edit.is_active}
                          onChange={(e) =>
                            setEdit({ ...edit, is_active: e.target.checked })
                          }
                        />
                        Active
                      </label>
                    </td>
                    <td className="py-2 text-right whitespace-nowrap">
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() =>
                          void run(() =>
                            feeService.updateFeeType(t.id, input(edit)),
                          ).then((ok) => ok && setEditing(null))
                        }
                        className="btn btn-primary btn-sm mr-2"
                      >
                        Save
                      </button>
                      <button
                        type="button"
                        onClick={() => setEditing(null)}
                        className="btn btn-secondary btn-sm"
                      >
                        Cancel
                      </button>
                    </td>
                  </tr>
                ) : (
                  <tr key={t.id} className="table-row">
                    <td className="py-2 pr-3 font-medium">{t.name}</td>
                    <td className="py-2 pr-3 text-slate-500">
                      {t.code ?? "—"}
                    </td>
                    <td className="py-2 pr-3">
                      {labelOf(FEE_CATEGORIES, t.category)}
                    </td>
                    <td className="py-2 pr-3 text-slate-500 tabular-nums">
                      {t.structures_count ?? 0}{" "}
                      {t.structures_count === 1 ? "class" : "classes"}
                    </td>
                    <td className="py-2 pr-3">
                      <span
                        className={
                          t.is_active ? "badge badge-green" : "badge badge-gray"
                        }
                      >
                        {t.is_active ? "Active" : "Off"}
                      </span>
                    </td>
                    {canEdit && (
                      <td className="py-2 text-right whitespace-nowrap">
                        <button
                          type="button"
                          onClick={() => {
                            setEditing(t.id);
                            setEdit({
                              name: t.name,
                              code: t.code ?? "",
                              category: t.category,
                              is_active: t.is_active,
                            });
                          }}
                          className="btn btn-secondary btn-sm mr-2"
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => {
                            if (
                              window.confirm(`Delete the fee type ${t.name}?`)
                            )
                              void run(() => feeService.deleteFeeType(t.id));
                          }}
                          className="btn btn-ghost btn-sm text-red-600"
                        >
                          Delete
                        </button>
                      </td>
                    )}
                  </tr>
                ),
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

interface Row {
  amount: string;
  frequency: FeeFrequency;
  is_active: boolean;
}

function StructuresManager({ options }: { options: FeeOptions }) {
  const { can } = useAuth();
  const canEdit = can("fee.create");
  const [yearId, setYearId] = useState(
    String(
      options.academic_years.find((y) => y.is_current)?.id ??
        options.academic_years[0]?.id ??
        "",
    ),
  );
  const [classId, setClassId] = useState(String(options.classes[0]?.id ?? ""));
  const [structures, setStructures] = useState<FeeStructure[] | null>(null);
  const [loadedFor, setLoadedFor] = useState("");
  const [rows, setRows] = useState<Record<number, Row>>({});
  const [adding, setAdding] = useState({
    fee_type_id: "",
    amount: "",
    frequency: "monthly" as FeeFrequency,
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const apply = useCallback((list: FeeStructure[], key: string) => {
    setStructures(list);
    setLoadedFor(key);
    setRows(
      Object.fromEntries(
        list.map((s) => [
          s.id,
          { amount: s.amount, frequency: s.frequency, is_active: s.is_active },
        ]),
      ),
    );
  }, []);

  // State set only in promise callbacks.
  useEffect(() => {
    if (!yearId || !classId) return;
    let active = true;
    feeService
      .listStructures(Number(yearId), Number(classId))
      .then((list) => {
        if (active) apply(list, `${yearId}:${classId}`);
      })
      .catch((err) => {
        if (active) setError(errorMessage(err));
      });
    return () => {
      active = false;
    };
  }, [yearId, classId, apply]);

  async function run(task: () => Promise<unknown>, success: string) {
    setBusy(true);
    setError(null);
    setNotice(null);
    try {
      await task();
      apply(
        await feeService.listStructures(Number(yearId), Number(classId)),
        `${yearId}:${classId}`,
      );
      setNotice(success);
      return true;
    } catch (err) {
      setError(errorMessage(err));
      return false;
    } finally {
      setBusy(false);
    }
  }

  const loading = !structures || loadedFor !== `${yearId}:${classId}`;
  const usedTypes = new Set(structures?.map((s) => s.fee_type?.id));
  const freeTypes = options.fee_types.filter(
    (t) => t.is_active && !usedTypes.has(t.id),
  );
  const monthly = (structures ?? [])
    .filter((s) => s.is_active && s.frequency === "monthly")
    .reduce((sum, s) => sum + paisa(s.amount), 0);
  const className = options.classes.find((c) => String(c.id) === classId)?.name;

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <select
          aria-label="Academic year"
          value={yearId}
          onChange={(e) => setYearId(e.target.value)}
          className={inputClass}
        >
          {options.academic_years.map((y) => (
            <option key={y.id} value={y.id}>
              {y.name}
              {y.is_current ? " (current)" : ""}
            </option>
          ))}
        </select>
        <select
          aria-label="Class"
          value={classId}
          onChange={(e) => setClassId(e.target.value)}
          className={inputClass}
        >
          {options.classes.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        {!loading && (
          <span className="ml-auto text-sm text-slate-600 dark:text-slate-300">
            Every month:{" "}
            <strong className="tabular-nums">
              {formatMoney((monthly / 100).toFixed(2), options.currency)}
            </strong>
          </span>
        )}
      </div>

      {error && (
        <p role="alert" className="alert alert-error mb-4">
          {error}
        </p>
      )}
      {notice && <p className="alert alert-success mb-4">{notice}</p>}

      {loading ? (
        <InlineLoader />
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr className="table-head">
                <th className="py-2 pr-3 font-medium">Fee</th>
                <th className="py-2 pr-3 font-medium">Billed</th>
                <th className="py-2 pr-3 font-medium">Amount</th>
                <th className="py-2 pr-3 font-medium">Active</th>
                {canEdit && (
                  <th className="py-2 font-medium">
                    <span className="sr-only">Actions</span>
                  </th>
                )}
              </tr>
            </thead>
            <tbody>
              {structures.length === 0 && (
                <tr>
                  <td colSpan={5} className="py-3 text-sm text-slate-500">
                    No fees set for {className} in this year yet.
                  </td>
                </tr>
              )}
              {structures.map((s) => {
                const row = rows[s.id];
                if (!row) return null;
                const changed =
                  row.amount !== s.amount ||
                  row.frequency !== s.frequency ||
                  row.is_active !== s.is_active;
                return (
                  <tr key={s.id} className="table-row">
                    <td className="py-2 pr-3 font-medium">
                      {s.fee_type?.name}
                      {s.fee_type && !s.fee_type.is_active && (
                        <span className="block text-xs text-amber-600">
                          Fee type is off
                        </span>
                      )}
                    </td>
                    <td className="py-2 pr-3">
                      <select
                        aria-label={`How often ${s.fee_type?.name} is billed`}
                        disabled={!canEdit}
                        value={row.frequency}
                        onChange={(e) =>
                          setRows({
                            ...rows,
                            [s.id]: {
                              ...row,
                              frequency: e.target.value as FeeFrequency,
                            },
                          })
                        }
                        title={
                          FEE_FREQUENCIES.find((f) => f.value === row.frequency)
                            ?.hint
                        }
                        className={inputClass}
                      >
                        {FEE_FREQUENCIES.map((f) => (
                          <option key={f.value} value={f.value}>
                            {f.label}
                          </option>
                        ))}
                      </select>
                    </td>
                    <td className="py-2 pr-3">
                      <input
                        type="number"
                        inputMode="decimal"
                        aria-label={`Amount for ${s.fee_type?.name}`}
                        disabled={!canEdit}
                        min="0.01"
                        step="0.01"
                        value={row.amount}
                        onChange={(e) =>
                          setRows({
                            ...rows,
                            [s.id]: { ...row, amount: e.target.value },
                          })
                        }
                        className={`w-32 tabular-nums ${inputClass}`}
                      />
                    </td>
                    <td className="py-2 pr-3">
                      <input
                        type="checkbox"
                        aria-label={`${s.fee_type?.name} is active`}
                        disabled={!canEdit}
                        checked={row.is_active}
                        onChange={(e) =>
                          setRows({
                            ...rows,
                            [s.id]: { ...row, is_active: e.target.checked },
                          })
                        }
                      />
                    </td>
                    {canEdit && (
                      <td className="py-2 text-right whitespace-nowrap">
                        <button
                          type="button"
                          disabled={busy || !changed}
                          onClick={() =>
                            void run(
                              () => feeService.updateStructure(s.id, row),
                              `${s.fee_type?.name} saved.`,
                            )
                          }
                          className="btn btn-primary btn-sm mr-2"
                        >
                          Save
                        </button>
                        <button
                          type="button"
                          disabled={busy}
                          onClick={() => {
                            if (
                              window.confirm(
                                `Remove ${s.fee_type?.name} from ${className}?`,
                              )
                            )
                              void run(
                                () => feeService.deleteStructure(s.id),
                                `${s.fee_type?.name} removed.`,
                              );
                          }}
                          className="btn btn-ghost btn-sm text-red-600"
                        >
                          Remove
                        </button>
                      </td>
                    )}
                  </tr>
                );
              })}
              {canEdit && freeTypes.length > 0 && (
                <tr className="bg-slate-50/60 dark:bg-slate-800/30">
                  <td className="py-2 pr-3">
                    <select
                      aria-label="Fee type to add"
                      value={adding.fee_type_id}
                      onChange={(e) =>
                        setAdding({ ...adding, fee_type_id: e.target.value })
                      }
                      className={inputClass}
                    >
                      <option value="">Add a fee…</option>
                      {freeTypes.map((t) => (
                        <option key={t.id} value={t.id}>
                          {t.name}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="py-2 pr-3">
                    <select
                      aria-label="How often the new fee is billed"
                      value={adding.frequency}
                      onChange={(e) =>
                        setAdding({
                          ...adding,
                          frequency: e.target.value as FeeFrequency,
                        })
                      }
                      className={inputClass}
                    >
                      {FEE_FREQUENCIES.map((f) => (
                        <option key={f.value} value={f.value}>
                          {f.label}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="py-2 pr-3">
                    <input
                      type="number"
                      inputMode="decimal"
                      aria-label="Amount for the new fee"
                      min="0.01"
                      step="0.01"
                      placeholder="0.00"
                      value={adding.amount}
                      onChange={(e) =>
                        setAdding({ ...adding, amount: e.target.value })
                      }
                      className={`w-32 tabular-nums ${inputClass}`}
                    />
                  </td>
                  <td />
                  <td className="py-2 text-right">
                    <button
                      type="button"
                      disabled={busy || !adding.fee_type_id || !adding.amount}
                      onClick={() =>
                        void run(
                          () =>
                            feeService.createStructure({
                              fee_type_id: Number(adding.fee_type_id),
                              class_id: Number(classId),
                              academic_year_id: Number(yearId),
                              amount: adding.amount,
                              frequency: adding.frequency,
                              is_active: true,
                            }),
                          "Fee added.",
                        ).then(
                          (ok) =>
                            ok &&
                            setAdding({
                              fee_type_id: "",
                              amount: "",
                              frequency: "monthly",
                            }),
                        )
                      }
                      className="btn btn-primary btn-sm"
                    >
                      Add
                    </button>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
      <p className="mt-3 text-xs text-slate-500">
        {FEE_FREQUENCIES.map((f) => `${f.label}: ${f.hint.toLowerCase()}`).join(
          " · ",
        )}
        .
      </p>
    </div>
  );
}

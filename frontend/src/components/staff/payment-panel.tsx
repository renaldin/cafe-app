"use client";

import { useMutation } from "@tanstack/react-query";
import { Banknote, CreditCard, Landmark, Plus, QrCode, Trash2, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Field, Input, MoneyInput } from "@/components/ui/input";
import { api, errMsg } from "@/lib/api";
import { cn } from "@/lib/cn";
import { rp } from "@/lib/format";
import type { Bill } from "@/types";

type Line = { method: "cash" | "qris" | "transfer" | "card"; amount: number; received: number; reference: string };
const METHODS = [
  { v: "cash", label: "Tunai", icon: Banknote },
  { v: "qris", label: "QRIS", icon: QrCode },
  { v: "transfer", label: "Transfer", icon: Landmark },
  { v: "card", label: "Kartu", icon: CreditCard },
] as const;

function quickCash(due: number): number[] {
  const set = new Set<number>([due]);
  [5000, 10000, 20000, 50000, 100000].forEach((step) => set.add(Math.ceil(due / step) * step));
  [50000, 100000, 200000].forEach((v) => v >= due && set.add(v));
  return [...set].filter((v) => v >= due).sort((a, b) => a - b).slice(0, 6);
}

export function PaymentPanel({ bill, onPaid }: { bill: Bill; onPaid: (b: Bill) => void }) {
  const due = bill.balance_due;
  const [lines, setLines] = useState<Line[]>([{ method: "cash", amount: due, received: 0, reference: "" }]);
  const [persons, setPersons] = useState(1);

  const target = persons > 1 ? Math.ceil(due / persons) : due;
  const nonCash = lines.filter((l) => l.method !== "cash").reduce((s, l) => s + l.amount, 0);
  const cashReceived = lines.filter((l) => l.method === "cash").reduce((s, l) => s + l.received, 0);
  const cashNeeded = Math.max(0, target - nonCash);
  const change = Math.max(0, cashReceived - cashNeeded);
  const applied = nonCash + Math.min(cashReceived, cashNeeded);
  const hasCash = lines.some((l) => l.method === "cash");
  const ok = applied > 0 && nonCash <= due && (persons > 1 ? applied >= Math.min(target, due) : applied >= due);

  const quick = useMemo(() => quickCash(cashNeeded), [cashNeeded]);

  const pay = useMutation({
    mutationFn: async () =>
      (
        await api.post(`/bills/${bill.id}/payments`, {
          payments: lines.map((l) => ({ method: l.method, amount: l.method === "cash" ? Math.min(l.received, cashNeeded) : l.amount, received: l.method === "cash" ? l.received : undefined, reference: l.reference || undefined })),
        })
      ).data,
    onSuccess: (r) => {
      toast.success(r.message);
      onPaid(r.data);
      const newDue = (r.data as Bill).balance_due;
      setLines([{ method: "cash", amount: newDue, received: 0, reference: "" }]);
      if (persons > 1) setPersons(Math.max(1, persons - 1));
    },
    onError: (e) => toast.error(errMsg(e)),
  });

  const update = (i: number, patch: Partial<Line>) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, ...patch } : l)));

  return (
    <div className="space-y-4">
      <div className="rounded-2xl bg-primary-soft p-4 text-center">
        <p className="text-sm text-muted">{persons > 1 ? `Bagian 1 dari ${persons} orang` : "Sisa tagihan"}</p>
        <p className="text-3xl font-black text-primary">{rp(target)}</p>
        {persons > 1 && <p className="text-xs text-muted">Total sisa {rp(due)}</p>}
      </div>

      <div className="flex items-center gap-2 text-sm">
        <Users className="h-4 w-4 text-muted" />
        <span className="text-muted">Bagi rata per orang:</span>
        {[1, 2, 3, 4, 5].map((n) => (
          <button key={n} onClick={() => setPersons(n)} className={cn("h-8 w-8 rounded-lg border text-sm font-semibold", persons === n ? "border-primary bg-primary text-primary-fg" : "border-border")}>
            {n}
          </button>
        ))}
      </div>

      {lines.map((l, i) => (
        <div key={i} className="space-y-3 rounded-2xl border border-border p-3">
          <div className="flex items-center gap-2">
            <div className="grid flex-1 grid-cols-4 gap-1">
              {METHODS.map((m) => {
                const Icon = m.icon;
                return (
                  <button key={m.v} onClick={() => update(i, { method: m.v, amount: m.v === "cash" ? l.amount : Math.max(0, target - (nonCash - (l.method !== "cash" ? l.amount : 0))) })} className={cn("flex flex-col items-center gap-1 rounded-xl border py-2 text-xs font-semibold", l.method === m.v ? "border-primary bg-primary-soft text-primary" : "border-border text-muted")}>
                    <Icon className="h-4 w-4" /> {m.label}
                  </button>
                );
              })}
            </div>
            {lines.length > 1 && (
              <button onClick={() => setLines((ls) => ls.filter((_, j) => j !== i))} className="rounded-lg p-2 text-danger hover:bg-danger-soft" aria-label="Hapus baris pembayaran">
                <Trash2 className="h-4 w-4" />
              </button>
            )}
          </div>
          {l.method === "cash" ? (
            <>
              <Field label="Uang diterima">
                <MoneyInput value={l.received} onChange={(v) => update(i, { received: v })} />
              </Field>
              <div className="flex flex-wrap gap-1.5">
                {quick.map((v) => (
                  <button key={v} onClick={() => update(i, { received: v })} className="rounded-lg border border-border px-2.5 py-1.5 text-sm font-medium hover:bg-surface-2">
                    {v === cashNeeded ? "Uang pas" : rp(v)}
                  </button>
                ))}
              </div>
            </>
          ) : (
            <div className="grid grid-cols-2 gap-2">
              <Field label="Nominal">
                <MoneyInput value={l.amount} onChange={(v) => update(i, { amount: v })} />
              </Field>
              <Field label="No. referensi">
                <Input value={l.reference} onChange={(e) => update(i, { reference: e.target.value })} placeholder="Opsional" />
              </Field>
            </div>
          )}
        </div>
      ))}

      {lines.length < 4 && (
        <Button variant="ghost" size="sm" onClick={() => setLines((ls) => [...ls, { method: "qris", amount: Math.max(0, cashNeeded), received: 0, reference: "" }])}>
          <Plus className="h-4 w-4" /> Tambah metode (split payment)
        </Button>
      )}

      {hasCash && (
        <div className="flex items-center justify-between rounded-2xl bg-success-soft px-4 py-3">
          <span className="font-medium text-success">Kembalian</span>
          <span className="text-2xl font-black text-success">{rp(change)}</span>
        </div>
      )}

      <Button size="xl" className="w-full" disabled={!ok} loading={pay.isPending} onClick={() => pay.mutate()}>
        {persons > 1 ? `Bayar bagian ini · ${rp(applied)}` : `Bayar · ${rp(applied)}`}
      </Button>
    </div>
  );
}

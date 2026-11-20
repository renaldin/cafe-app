"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Wallet } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Field, MoneyInput, Textarea } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { api, errMsg } from "@/lib/api";
import { rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import { useAuth } from "@/stores/auth";

export type ShiftData = {
  id: number;
  opened_at: string;
  opening_cash: number;
  summary: { cash_sales: number; non_cash_sales: number; cash_refunds: number; expected_cash: number; bill_count: number; by_method: Record<string, { total: number; count: number }> };
} | null;

export function useCurrentShift() {
  const can = useCan();
  const setShift = useAuth((s) => s.setShift);
  return useQuery({
    queryKey: ["shift-current"],
    enabled: can("shift.manage"),
    queryFn: async () => {
      const d = (await api.get<{ data: ShiftData }>("/shifts/current")).data.data;
      setShift(d ? { id: d.id, opened_at: d.opened_at, opening_cash: d.opening_cash } : null);
      return d;
    },
  });
}

export function OpenShiftModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const qc = useQueryClient();
  const [cash, setCash] = useState(0);
  const [note, setNote] = useState("");
  const m = useMutation({
    mutationFn: async () => (await api.post("/shifts/open", { opening_cash: cash, note: note || undefined })).data,
    onSuccess: (r) => {
      toast.success(r.message);
      void qc.invalidateQueries({ queryKey: ["shift-current"] });
      onClose();
    },
    onError: (e) => toast.error(errMsg(e)),
  });
  return (
    <Modal open={open} onClose={onClose} title="Buka shift" description="Hitung uang modal di laci kasir sebelum mulai." size="sm" footer={<Button onClick={() => m.mutate()} loading={m.isPending}>Buka shift</Button>}>
      <div className="space-y-3">
        <Field label="Modal awal (uang di laci)">
          <MoneyInput value={cash} onChange={setCash} autoFocus />
        </Field>
        <Field label="Catatan (opsional)">
          <Textarea value={note} onChange={(e) => setNote(e.target.value)} className="min-h-[56px]" />
        </Field>
      </div>
    </Modal>
  );
}

export function ShiftBanner() {
  const can = useCan();
  const q = useCurrentShift();
  const [open, setOpen] = useState(false);
  if (!can("shift.manage") || q.isLoading) return null;
  if (q.data) {
    return (
      <div className="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-2xl border border-success/30 bg-success-soft px-4 py-2.5 text-sm">
        <span className="flex items-center gap-2 font-semibold text-success">
          <Wallet className="h-4 w-4" /> Shift aktif
        </span>
        <span className="text-muted">Tunai: <b className="text-fg">{rp(q.data.summary.cash_sales)}</b></span>
        <span className="text-muted">Non-tunai: <b className="text-fg">{rp(q.data.summary.non_cash_sales)}</b></span>
        <span className="text-muted">Transaksi: <b className="text-fg">{q.data.summary.bill_count}</b></span>
      </div>
    );
  }
  return (
    <>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-warning/40 bg-warning-soft px-4 py-3 text-sm">
        <span className="font-medium text-warning">Anda belum membuka shift. Pembayaran tidak dapat diproses tanpa shift aktif.</span>
        <Button size="sm" onClick={() => setOpen(true)}>
          <Wallet className="h-4 w-4" /> Buka shift
        </Button>
      </div>
      <OpenShiftModal open={open} onClose={() => setOpen(false)} />
    </>
  );
}

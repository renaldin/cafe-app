"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Lock, Wallet } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { OpenShiftModal, useCurrentShift } from "@/components/staff/shift-gate";
import { Button } from "@/components/ui/button";
import { Badge, Card, CardHeader, EmptyState, StatCard } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, MoneyInput, Textarea } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { api, errMsg, type Paginated } from "@/lib/api";
import { cn } from "@/lib/cn";
import { PAY_METHOD, fdatetime, rp } from "@/lib/format";

type ShiftRow = { id: number; user: { name: string } | null; status: string; opened_at: string; closed_at: string | null; opening_cash: number; cash_sales: number; non_cash_sales: number; expected_cash: number; counted_cash: number | null; difference: number | null };

export default function ShiftPage() {
  const qc = useQueryClient();
  const current = useCurrentShift();
  const [page, setPage] = useState(1);
  const [openModal, setOpenModal] = useState(false);
  const [closeModal, setCloseModal] = useState(false);
  const [counted, setCounted] = useState(0);
  const [note, setNote] = useState("");
  const list = useQuery({ queryKey: ["shifts", page], queryFn: async () => (await api.get<Paginated<ShiftRow>>("/shifts", { params: { page } })).data });

  const close = useMutation({
    mutationFn: async () => (await api.post(`/shifts/${current.data!.id}/close`, { counted_cash: counted, note: note || undefined })).data,
    onSuccess: (r) => {
      const d = r.data as ShiftRow;
      toast.success(`Shift ditutup. Selisih: ${rp(d.difference ?? 0)}`);
      setCloseModal(false);
      void qc.invalidateQueries({ queryKey: ["shift-current"] });
      void qc.invalidateQueries({ queryKey: ["shifts"] });
    },
    onError: (e) => toast.error(errMsg(e)),
  });

  const s = current.data;
  const columns: Column<ShiftRow>[] = [
    { header: "Kasir", cell: (r) => r.user?.name },
    { header: "Buka", cell: (r) => fdatetime(r.opened_at) },
    { header: "Tutup", cell: (r) => (r.closed_at ? fdatetime(r.closed_at) : <Badge className="bg-success-soft text-success">Aktif</Badge>) },
    { header: "Modal", cell: (r) => rp(r.opening_cash) },
    { header: "Tunai", cell: (r) => rp(r.cash_sales) },
    { header: "Non-tunai", cell: (r) => rp(r.non_cash_sales) },
    { header: "Kas sistem", cell: (r) => rp(r.expected_cash) },
    { header: "Kas fisik", cell: (r) => (r.counted_cash !== null ? rp(r.counted_cash) : "-") },
    { header: "Selisih", cell: (r) => (r.difference !== null ? <span className={cn("font-semibold", r.difference < 0 ? "text-danger" : r.difference > 0 ? "text-warning" : "text-success")}>{rp(r.difference)}</span> : "-") },
  ];
  const diff = s ? counted - s.summary.expected_cash : 0;

  return (
    <div>
      <PageHeader title="Shift Kasir" description="Buka shift dengan modal awal, tutup dengan menghitung uang fisik." actions={s ? <Button variant="danger" onClick={() => { setCounted(s.summary.expected_cash); setCloseModal(true); }}><Lock className="h-4 w-4" /> Tutup shift</Button> : <Button onClick={() => setOpenModal(true)}><Wallet className="h-4 w-4" /> Buka shift</Button>} />
      {s ? (
        <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Modal awal" value={rp(s.opening_cash)} hint={`Dibuka ${fdatetime(s.opened_at)}`} />
          <StatCard label="Penjualan tunai" value={rp(s.summary.cash_sales)} tone="success" />
          <StatCard label="Non-tunai" value={rp(s.summary.non_cash_sales)} tone="info" hint={Object.entries(s.summary.by_method ?? {}).filter(([k]) => k !== "cash").map(([k, v]) => `${PAY_METHOD[k]}: ${rp(v.total)}`).join(" · ")} />
          <StatCard label="Kas seharusnya" value={rp(s.summary.expected_cash)} tone="warning" hint={s.summary.cash_refunds ? `Refund tunai ${rp(s.summary.cash_refunds)}` : `${s.summary.bill_count} transaksi`} />
        </div>
      ) : (
        <Card className="mb-6"><EmptyState icon={<Wallet className="h-6 w-6" />} title="Tidak ada shift aktif" description="Buka shift untuk mulai menerima pembayaran." /></Card>
      )}
      <Card>
        <CardHeader title="Riwayat shift" />
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} />
      </Card>
      <OpenShiftModal open={openModal} onClose={() => setOpenModal(false)} />
      <Modal open={closeModal} onClose={() => setCloseModal(false)} title="Tutup shift" size="sm" footer={<Button variant="danger" loading={close.isPending} onClick={() => close.mutate()}>Tutup shift</Button>}>
        {s && (
          <div className="space-y-3">
            <div className="rounded-xl bg-surface-2 p-3 text-sm">
              <div className="flex justify-between"><span>Kas seharusnya</span><b>{rp(s.summary.expected_cash)}</b></div>
            </div>
            <Field label="Uang fisik di laci"><MoneyInput value={counted} onChange={setCounted} autoFocus /></Field>
            <div className={cn("flex justify-between rounded-xl px-3 py-2 text-sm font-semibold", diff === 0 ? "bg-success-soft text-success" : "bg-danger-soft text-danger")}>
              <span>Selisih</span><span>{rp(diff)}</span>
            </div>
            <Field label="Catatan"><Textarea value={note} onChange={(e) => setNote(e.target.value)} className="min-h-[56px]" /></Field>
          </div>
        )}
      </Modal>
    </div>
  );
}

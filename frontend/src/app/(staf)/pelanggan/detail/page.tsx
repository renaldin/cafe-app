"use client";

import { Gift, Pencil } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card, CardHeader, Skeleton, StatCard } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Textarea } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet } from "@/hooks/useApi";
import { BILL_STATUS, fdatetime, num, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Bill, Customer } from "@/types";

type Detail = { customer: Customer; bills: Bill[]; points: { id: number; points: number; type: string; note: string | null; created_at: string }[] };

function CustomerDetail() {
  const id = useSearchParams().get("id");
  const router = useRouter();
  const can = useCan();
  const q = useGet<Detail>(["customer", id], `/customers/${id}`, undefined, { enabled: !!id });
  const [edit, setEdit] = useState<Partial<Customer> | null>(null);
  const [adj, setAdj] = useState<{ points: number; note: string } | null>(null);
  const save = useApiMutation<Partial<Customer>>("put", `/customers/${id}`, [["customer", id], ["customers"]], () => setEdit(null));
  const points = useApiMutation<{ points: number; note: string }>("post", `/customers/${id}/points`, [["customer", id]], () => setAdj(null));
  if (q.isLoading || !q.data) return <Skeleton className="h-96" />;
  const c = q.data.customer;
  const columns: Column<Bill>[] = [
    { header: "Invoice", cell: (b) => <b>{b.invoice_no}</b> },
    { header: "Waktu", cell: (b) => fdatetime(b.opened_at) },
    { header: "Tipe", cell: (b) => b.type_label },
    { header: "Total", cell: (b) => rp(b.total) },
    { header: "Status", cell: (b) => <Badge className={BILL_STATUS[b.status]?.className}>{BILL_STATUS[b.status]?.text}</Badge> },
  ];
  return (
    <div>
      <PageHeader title={c.name} description={`${c.phone}${c.email ? ` Â· ${c.email}` : ""}`} actions={can("customer.manage") && <><Button variant="outline" onClick={() => setAdj({ points: 0, note: "" })}><Gift className="h-4 w-4" /> Sesuaikan poin</Button><Button variant="outline" onClick={() => setEdit(c)}><Pencil className="h-4 w-4" /> Ubah</Button></>} />
      <div className="mb-4 grid gap-3 sm:grid-cols-3">
        <StatCard label="Poin" value={num(c.points)} tone="primary" />
        <StatCard label="Total belanja" value={rp(c.total_spent)} tone="success" />
        <StatCard label="Kunjungan" value={num(c.visit_count)} tone="info" hint={c.last_visit_at ? `Terakhir ${fdatetime(c.last_visit_at)}` : undefined} />
      </div>
      <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
        <Card>
          <CardHeader title="Riwayat pesanan" />
          <DataTable columns={columns} data={q.data.bills} onRowClick={(b) => router.push(`/kasir/bill/?id=${b.id}`)} />
        </Card>
        <Card>
          <CardHeader title="Riwayat poin" />
          <ul className="divide-y divide-border text-sm">
            {q.data.points.map((p) => (
              <li key={p.id} className="flex justify-between px-5 py-2.5">
                <span><span className="block">{p.note}</span><span className="text-xs text-muted">{fdatetime(p.created_at)}</span></span>
                <b className={p.points < 0 ? "text-danger" : "text-success"}>{p.points > 0 ? "+" : ""}{p.points}</b>
              </li>
            ))}
            {!q.data.points.length && <li className="px-5 py-6 text-center text-muted">Belum ada transaksi poin</li>}
          </ul>
        </Card>
      </div>
      <Modal open={!!edit} onClose={() => setEdit(null)} title="Ubah pelanggan" size="sm" footer={<Button loading={save.isPending} onClick={() => edit && save.mutate(edit)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-3">
            <Field label="Nama"><Input value={edit.name ?? ""} onChange={(e) => setEdit({ ...edit, name: e.target.value })} /></Field>
            <Field label="Nomor WA"><Input value={edit.phone ?? ""} onChange={(e) => setEdit({ ...edit, phone: e.target.value })} /></Field>
            <Field label="Email"><Input value={edit.email ?? ""} onChange={(e) => setEdit({ ...edit, email: e.target.value })} /></Field>
            <Field label="Catatan"><Textarea value={edit.note ?? ""} onChange={(e) => setEdit({ ...edit, note: e.target.value })} /></Field>
          </div>
        )}
      </Modal>
      <Modal open={!!adj} onClose={() => setAdj(null)} title="Penyesuaian poin" size="sm" footer={<Button loading={points.isPending} disabled={!adj?.points || (adj?.note.length ?? 0) < 3} onClick={() => adj && points.mutate(adj)}>Simpan</Button>}>
        {adj && (
          <div className="space-y-3">
            <Field label="Poin (+/âˆ’)"><Input type="number" value={adj.points || ""} onChange={(e) => setAdj({ ...adj, points: Number(e.target.value) })} /></Field>
            <Field label="Alasan *"><Input value={adj.note} onChange={(e) => setAdj({ ...adj, note: e.target.value })} /></Field>
          </div>
        )}
      </Modal>
    </div>
  );
}

export default function CustomerDetailPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96" />}>
      <CustomerDetail />
    </Suspense>
  );
}

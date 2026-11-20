"use client";

import { Plus } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useGet, usePaged } from "@/hooks/useApi";
import { fdate, num, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";

type Purchase = { id: number; purchase_no: string; date: string; total: number; note: string | null; supplier: { name: string } | null; user: { name: string } | null; items_count: number };
type PurchaseDetail = Purchase & { items: { id: number; qty: number; unit_cost: number; subtotal: number; ingredient: { name: string; unit: { symbol: string } } }[] };

export default function PembelianPage() {
  const can = useCan();
  const [page, setPage] = useState(1);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [detailId, setDetailId] = useState<number | null>(null);
  const list = usePaged<Purchase>(["purchases"], "/purchases", { page, from: from || undefined, to: to || undefined });
  const detail = useGet<PurchaseDetail>(["purchase", detailId], `/purchases/${detailId}`, undefined, { enabled: !!detailId });
  const columns: Column<Purchase>[] = [
    { header: "No.", cell: (p) => <b>{p.purchase_no}</b> },
    { header: "Tanggal", cell: (p) => fdate(p.date) },
    { header: "Supplier", cell: (p) => p.supplier?.name ?? "-" },
    { header: "Item", cell: (p) => p.items_count },
    { header: "Total", cell: (p) => rp(p.total) },
    { header: "Oleh", cell: (p) => p.user?.name },
  ];
  return (
    <div>
      <PageHeader title="Pembelian / Stok Masuk" description="Pembelian menambah stok dan memperbarui harga rata-rata bahan." actions={can("stock.manage") && <Link href="/stok/pembelian/baru/"><Button><Plus className="h-4 w-4" /> Pembelian</Button></Link>} />
      <Card>
        <div className="flex flex-wrap gap-2 border-b border-border p-4">
          <Field label="Dari"><Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></Field>
          <Field label="Sampai"><Input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} onRowClick={(p) => setDetailId(p.id)} />
      </Card>
      <Modal open={!!detailId} onClose={() => setDetailId(null)} title={detail.data?.purchase_no} description={detail.data ? `${fdate(detail.data.date)} · ${detail.data.supplier?.name ?? "Tanpa supplier"}` : ""}>
        <ul className="divide-y divide-border text-sm">
          {detail.data?.items.map((i) => (
            <li key={i.id} className="flex justify-between py-2"><span>{i.ingredient.name} · {num(i.qty, 3)} {i.ingredient.unit.symbol} × {rp(i.unit_cost)}</span><b>{rp(i.subtotal)}</b></li>
          ))}
        </ul>
        {detail.data && <p className="mt-3 text-right font-bold">Total {rp(detail.data.total)}</p>}
      </Modal>
    </div>
  );
}

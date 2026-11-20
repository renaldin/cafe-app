"use client";

import { CheckCircle2, Plus } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet, usePaged } from "@/hooks/useApi";
import { cn } from "@/lib/cn";
import { fdate, num, today } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Ingredient } from "@/types";

type Opname = { id: number; opname_no: string; date: string; status: string; note: string | null; items_count: number; user: { name: string } | null };
type OpnameDetail = Opname & { items: { id: number; system_qty: number; counted_qty: number; difference: number; ingredient: { name: string; unit: { symbol: string } } }[] };

export default function OpnamePage() {
  const can = useCan();
  const [page, setPage] = useState(1);
  const list = usePaged<Opname>(["opnames"], "/stock-opnames", { page });
  const [creating, setCreating] = useState(false);
  const [detailId, setDetailId] = useState<number | null>(null);
  const ings = useGet<Ingredient[]>(["ingredients-all"], "/ingredients", { all: 1 }, { enabled: creating });
  const detail = useGet<OpnameDetail>(["opname", detailId], `/stock-opnames/${detailId}`, undefined, { enabled: !!detailId });
  const [counts, setCounts] = useState<Record<number, string>>({});
  const [date, setDate] = useState(today());
  const [note, setNote] = useState("");
  const create = useApiMutation<unknown>("post", "/stock-opnames", [["opnames"]], (d) => { setCreating(false); setCounts({}); setDetailId((d as Opname).id); });
  const finalize = useApiMutation<{ id: number }>("post", (v) => `/stock-opnames/${v.id}/finalize`, [["opnames"], ["opname", detailId], ["ingredients"]]);

  const columns: Column<Opname>[] = [
    { header: "No.", cell: (o) => <b>{o.opname_no}</b> },
    { header: "Tanggal", cell: (o) => fdate(o.date) },
    { header: "Item", cell: (o) => o.items_count },
    { header: "Status", cell: (o) => <Badge className={o.status === "final" ? "bg-success-soft text-success" : "bg-warning-soft text-warning"}>{o.status === "final" ? "Final" : "Draft"}</Badge> },
    { header: "Oleh", cell: (o) => o.user?.name },
  ];

  return (
    <div>
      <PageHeader title="Stock Opname" description="Hitung stok fisik, bandingkan dengan sistem, lalu finalisasi untuk menyesuaikan stok." actions={can("stock.manage") && <Button onClick={() => setCreating(true)}><Plus className="h-4 w-4" /> Opname baru</Button>} />
      <Card><DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} onRowClick={(o) => setDetailId(o.id)} /></Card>

      <Modal open={creating} onClose={() => setCreating(false)} size="lg" title="Opname baru" description="Isi stok fisik hasil hitung. Bahan yang dikosongkan tidak diopname." footer={<Button loading={create.isPending} disabled={!Object.values(counts).some((v) => v !== "")} onClick={() => create.mutate({ date, note: note || null, items: Object.entries(counts).filter(([, v]) => v !== "").map(([id, v]) => ({ ingredient_id: Number(id), counted_qty: Number(v) })) })}>Simpan draft</Button>}>
        <div className="mb-3 grid grid-cols-2 gap-2">
          <Field label="Tanggal"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label="Catatan"><Input value={note} onChange={(e) => setNote(e.target.value)} /></Field>
        </div>
        <table className="w-full text-sm">
          <thead><tr className="text-left text-xs uppercase text-muted"><th className="py-2">Bahan</th><th>Sistem</th><th>Fisik</th><th>Selisih</th></tr></thead>
          <tbody>
            {ings.data?.map((i) => {
              const v = counts[i.id] ?? "";
              const diff = v === "" ? null : Number(v) - i.stock;
              return (
                <tr key={i.id} className="border-t border-border">
                  <td className="py-1.5">{i.name}</td>
                  <td>{num(i.stock, 3)} {i.unit}</td>
                  <td><Input type="number" step="0.001" min={0} className="h-8 w-28" value={v} onChange={(e) => setCounts({ ...counts, [i.id]: e.target.value })} aria-label={`Stok fisik ${i.name}`} /></td>
                  <td className={cn(diff !== null && diff !== 0 && (diff < 0 ? "text-danger" : "text-success"))}>{diff === null ? "-" : num(diff, 3)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </Modal>

      <Modal open={!!detailId} onClose={() => setDetailId(null)} size="lg" title={detail.data?.opname_no} description={detail.data ? `${fdate(detail.data.date)} · ${detail.data.status === "final" ? "Final" : "Draft"}` : ""} footer={detail.data?.status === "draft" && can("stock.manage") && <Button loading={finalize.isPending} onClick={async () => { const r = await confirm({ title: "Finalisasi opname?", description: "Stok sistem akan disesuaikan dengan hasil hitung fisik. Tindakan ini tidak bisa dibatalkan." }); if (r.ok) finalize.mutate({ id: detailId! }); }}><CheckCircle2 className="h-4 w-4" /> Finalisasi</Button>}>
        <table className="w-full text-sm">
          <thead><tr className="text-left text-xs uppercase text-muted"><th className="py-2">Bahan</th><th>Sistem</th><th>Fisik</th><th>Selisih</th></tr></thead>
          <tbody>
            {detail.data?.items.map((i) => (
              <tr key={i.id} className="border-t border-border">
                <td className="py-2">{i.ingredient.name}</td>
                <td>{num(i.system_qty, 3)} {i.ingredient.unit.symbol}</td>
                <td>{num(i.counted_qty, 3)}</td>
                <td className={cn(i.difference < 0 ? "text-danger" : i.difference > 0 ? "text-success" : "")}>{num(i.difference, 3)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Modal>
    </div>
  );
}

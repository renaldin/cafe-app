"use client";

import { Minus, SlidersHorizontal, Trash } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Select } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet, usePaged } from "@/hooks/useApi";
import { cn } from "@/lib/cn";
import { fdatetime, num } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Ingredient } from "@/types";

type Movement = { id: number; type: string; qty: number; stock_after: number; ingredient: string; unit: string; reason: string | null; user: string | null; created_at: string };
const TYPES: Record<string, { label: string; cls: string }> = {
  purchase: { label: "Pembelian", cls: "bg-success-soft text-success" },
  sale: { label: "Penjualan", cls: "bg-info-soft text-info" },
  adjustment: { label: "Penyesuaian", cls: "bg-surface-3 text-muted" },
  opname: { label: "Opname", cls: "bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300" },
  waste: { label: "Waste", cls: "bg-danger-soft text-danger" },
  return: { label: "Retur", cls: "bg-warning-soft text-warning" },
};

export default function MutasiPage() {
  const can = useCan();
  const [page, setPage] = useState(1);
  const [type, setType] = useState("");
  const [ingredient, setIngredient] = useState("");
  const list = usePaged<Movement>(["movements"], "/stock/movements", { page, type: type || undefined, ingredient_id: ingredient || undefined });
  const ings = useGet<Ingredient[]>(["ingredients-all"], "/ingredients", { all: 1 });
  const [modal, setModal] = useState<null | "adjust" | "waste">(null);
  const [form, setForm] = useState({ ingredient_id: 0, qty: 0, reason: "" });
  const submit = useApiMutation<typeof form>("post", () => (modal === "waste" ? "/stock/waste" : "/stock/adjustments"), [["movements"], ["ingredients"], ["ingredients-all"]], () => setModal(null));

  const columns: Column<Movement>[] = [
    { header: "Waktu", cell: (m) => fdatetime(m.created_at) },
    { header: "Bahan", cell: (m) => <b>{m.ingredient}</b> },
    { header: "Jenis", cell: (m) => <Badge className={TYPES[m.type]?.cls}>{TYPES[m.type]?.label ?? m.type}</Badge> },
    { header: "Jumlah", cell: (m) => <span className={cn("font-semibold", m.qty < 0 ? "text-danger" : "text-success")}>{m.qty > 0 ? "+" : ""}{num(m.qty, 3)} {m.unit}</span> },
    { header: "Stok akhir", cell: (m) => `${num(m.stock_after, 3)} ${m.unit}` },
    { header: "Keterangan", cell: (m) => <span className="text-muted">{m.reason}</span> },
    { header: "Oleh", cell: (m) => m.user ?? "Sistem" },
  ];
  const open = (m: "adjust" | "waste") => { setForm({ ingredient_id: ings.data?.[0]?.id ?? 0, qty: 0, reason: "" }); setModal(m); };

  return (
    <div>
      <PageHeader title="Mutasi Stok & Waste" description="Riwayat semua pergerakan stok bahan." actions={can("stock.manage") && <><Button variant="outline" onClick={() => open("adjust")}><SlidersHorizontal className="h-4 w-4" /> Penyesuaian</Button><Button variant="danger" onClick={() => open("waste")}><Trash className="h-4 w-4" /> Catat waste</Button></>} />
      <Card>
        <div className="flex flex-col gap-2 border-b border-border p-4 sm:flex-row">
          <Select value={type} onChange={(e) => { setType(e.target.value); setPage(1); }} className="sm:w-48" aria-label="Jenis">
            <option value="">Semua jenis</option>
            {Object.entries(TYPES).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
          </Select>
          <Select value={ingredient} onChange={(e) => { setIngredient(e.target.value); setPage(1); }} className="sm:w-64" aria-label="Bahan">
            <option value="">Semua bahan</option>
            {ings.data?.map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
          </Select>
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} />
      </Card>
      <Modal open={!!modal} onClose={() => setModal(null)} size="sm" title={modal === "waste" ? "Catat waste / bahan rusak" : "Penyesuaian stok"} footer={<Button variant={modal === "waste" ? "danger" : "primary"} loading={submit.isPending} disabled={!form.qty || form.reason.length < 3} onClick={() => submit.mutate(form)}>Simpan</Button>}>
        <div className="space-y-3">
          <Field label="Bahan">
            <Select value={form.ingredient_id} onChange={(e) => setForm({ ...form, ingredient_id: Number(e.target.value) })}>
              {ings.data?.map((i) => <option key={i.id} value={i.id}>{i.name} (stok {num(i.stock, 3)} {i.unit})</option>)}
            </Select>
          </Field>
          <Field label={modal === "waste" ? "Jumlah terbuang" : "Jumlah (+ tambah / − kurang)"} hint={modal === "adjust" ? "Gunakan angka negatif untuk mengurangi stok." : undefined}>
            <div className="flex items-center gap-2">
              {modal === "waste" && <Minus className="h-4 w-4 text-danger" />}
              <Input type="number" step="0.001" value={form.qty || ""} onChange={(e) => setForm({ ...form, qty: Number(e.target.value) })} />
            </div>
          </Field>
          <Field label="Alasan *"><Input value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} placeholder={modal === "waste" ? "Contoh: susu basi" : "Contoh: koreksi input"} /></Field>
        </div>
      </Modal>
    </div>
  );
}

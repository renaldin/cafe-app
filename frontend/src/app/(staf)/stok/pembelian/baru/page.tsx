"use client";

import { Plus, X } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Field, Input, Select } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet } from "@/hooks/useApi";
import { rp, today } from "@/lib/format";
import type { Ingredient } from "@/types";

type Row = { ingredient_id: number; qty: number; unit_cost: number };

export default function PembelianBaruPage() {
  const router = useRouter();
  const ings = useGet<Ingredient[]>(["ingredients-all"], "/ingredients", { all: 1 });
  const sups = useGet<{ id: number; name: string }[]>(["suppliers-all"], "/suppliers", { all: 1 });
  const [supplier, setSupplier] = useState("");
  const [date, setDate] = useState(today());
  const [note, setNote] = useState("");
  const [rows, setRows] = useState<Row[]>([]);
  const save = useApiMutation<unknown>("post", "/purchases", [["purchases"], ["ingredients"]], () => router.push("/stok/pembelian/"));
  const total = rows.reduce((s, r) => s + r.qty * r.unit_cost, 0);
  const upd = (i: number, p: Partial<Row>) => setRows(rows.map((r, j) => (j === i ? { ...r, ...p } : r)));

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader title="Pembelian baru" />
      <Card className="space-y-4 p-5">
        <div className="grid gap-3 sm:grid-cols-3">
          <Field label="Supplier">
            <Select value={supplier} onChange={(e) => setSupplier(e.target.value)}>
              <option value="">— Tanpa supplier —</option>
              {sups.data?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </Select>
          </Field>
          <Field label="Tanggal"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label="Catatan / no. nota"><Input value={note} onChange={(e) => setNote(e.target.value)} /></Field>
        </div>
        <div className="space-y-2">
          <div className="hidden grid-cols-[1fr_120px_160px_120px_40px] gap-2 text-xs font-semibold uppercase text-muted sm:grid"><span>Bahan</span><span>Jumlah</span><span>Harga / satuan</span><span>Subtotal</span><span /></div>
          {rows.map((r, i) => {
            const ing = ings.data?.find((x) => x.id === r.ingredient_id);
            return (
              <div key={i} className="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_120px_160px_120px_40px] sm:items-center">
                <Select className="col-span-2 sm:col-span-1" value={r.ingredient_id} onChange={(e) => { const x = ings.data?.find((y) => y.id === Number(e.target.value)); upd(i, { ingredient_id: Number(e.target.value), unit_cost: x?.cost_per_unit ?? 0 }); }} aria-label="Bahan">
                  {ings.data?.map((x) => <option key={x.id} value={x.id}>{x.name} ({x.unit})</option>)}
                </Select>
                <Input type="number" step="0.001" min={0} value={r.qty || ""} placeholder={ing?.unit} onChange={(e) => upd(i, { qty: Number(e.target.value) })} aria-label="Jumlah" />
                <Input type="number" step="0.01" min={0} value={r.unit_cost || ""} onChange={(e) => upd(i, { unit_cost: Number(e.target.value) })} aria-label="Harga per satuan" />
                <span className="text-sm font-semibold">{rp(r.qty * r.unit_cost)}</span>
                <Button variant="ghost" size="icon" onClick={() => setRows(rows.filter((_, j) => j !== i))} aria-label="Hapus baris"><X className="h-4 w-4" /></Button>
              </div>
            );
          })}
          <Button variant="ghost" size="sm" disabled={!ings.data?.length} onClick={() => setRows([...rows, { ingredient_id: ings.data![0].id, qty: 0, unit_cost: ings.data![0].cost_per_unit }])}><Plus className="h-4 w-4" /> Tambah bahan</Button>
        </div>
        <div className="flex items-center justify-between border-t border-border pt-4">
          <p className="text-lg font-bold">Total {rp(total)}</p>
          <Button loading={save.isPending} disabled={!rows.length || rows.some((r) => r.qty <= 0)} onClick={() => save.mutate({ supplier_id: supplier ? Number(supplier) : null, date, note: note || null, items: rows })}>Simpan pembelian</Button>
        </div>
      </Card>
    </div>
  );
}

"use client";

import { AlertTriangle, Pencil, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Checkbox, Field, Input, Select, Switch } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { useApiMutation, useGet, usePaged } from "@/hooks/useApi";
import { useDebounce } from "@/hooks/useDebounce";
import { num, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Ingredient, Unit } from "@/types";

type Form = Partial<Ingredient> & { initial_stock?: number };

export default function BahanPage() {
  const can = useCan();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [low, setLow] = useState(false);
  const s = useDebounce(search);
  const list = usePaged<Ingredient>(["ingredients"], "/ingredients", { page, search: s || undefined, low: low ? 1 : undefined });
  const units = useGet<Unit[]>(["units"], "/units");
  const [edit, setEdit] = useState<Form | null>(null);
  const save = useApiMutation<Form>(edit?.id ? "put" : "post", (v) => (v.id ? `/ingredients/${v.id}` : "/ingredients"), [["ingredients"], ["ingredients-all"]], () => setEdit(null));
  const del = useApiMutation<{ id: number }>("delete", (v) => `/ingredients/${v.id}`, [["ingredients"]]);

  const columns: Column<Ingredient>[] = [
    { header: "Bahan", cell: (i) => <span className="font-semibold">{i.name} {i.is_key && <Badge className="bg-primary-soft text-primary">Utama</Badge>}</span> },
    { header: "Stok", cell: (i) => <span className={i.is_low ? "font-bold text-danger" : ""}>{num(i.stock, 3)} {i.unit} {i.is_low && <AlertTriangle className="inline h-3.5 w-3.5" />}</span> },
    { header: "Minimum", cell: (i) => `${num(i.min_stock, 3)} ${i.unit}` },
    { header: "HPP / satuan", cell: (i) => rp(i.cost_per_unit) },
    { header: "Nilai stok", cell: (i) => rp(i.stock_value) },
    {
      header: "",
      className: "text-right",
      cell: (i) =>
        can("stock.manage") && (
          <div className="flex justify-end gap-1">
            <Button size="sm" variant="ghost" onClick={() => setEdit(i)} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button>
            <Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus ${i.name}?`, danger: true }); if (r.ok) del.mutate({ id: i.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button>
          </div>
        ),
    },
  ];

  return (
    <div>
      <PageHeader title="Bahan Baku" description="Stok berkurang otomatis dari resep saat pesanan dibayar/disajikan." actions={can("stock.manage") && <Button onClick={() => setEdit({ unit_id: units.data?.[0]?.id, min_stock: 0, cost_per_unit: 0, is_key: false, initial_stock: 0 })}><Plus className="h-4 w-4" /> Bahan</Button>} />
      <Card>
        <div className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center">
          <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Cari bahan..." className="flex-1" />
          <Checkbox checked={low} onChange={(v) => { setLow(v); setPage(1); }} label="Hanya stok menipis" />
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} />
      </Card>
      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit?.id ? "Ubah bahan" : "Bahan baru"} size="sm" footer={<Button loading={save.isPending} onClick={() => edit && save.mutate(edit)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-3">
            <Field label="Nama *"><Input value={edit.name ?? ""} onChange={(e) => setEdit({ ...edit, name: e.target.value })} /></Field>
            <div className="grid grid-cols-2 gap-2">
              <Field label="Satuan">
                <Select value={edit.unit_id} onChange={(e) => setEdit({ ...edit, unit_id: Number(e.target.value) })}>
                  {units.data?.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.symbol})</option>)}
                </Select>
              </Field>
              <Field label="Stok minimum"><Input type="number" step="0.001" min={0} value={edit.min_stock ?? 0} onChange={(e) => setEdit({ ...edit, min_stock: Number(e.target.value) })} /></Field>
            </div>
            <Field label="Harga beli per satuan (Rp)" hint="Diperbarui otomatis (rata-rata) saat input pembelian."><Input type="number" step="0.01" min={0} value={edit.cost_per_unit ?? 0} onChange={(e) => setEdit({ ...edit, cost_per_unit: Number(e.target.value) })} /></Field>
            {!edit.id && <Field label="Stok awal"><Input type="number" step="0.001" min={0} value={edit.initial_stock ?? 0} onChange={(e) => setEdit({ ...edit, initial_stock: Number(e.target.value) })} /></Field>}
            <Switch checked={!!edit.is_key} onChange={(v) => setEdit({ ...edit, is_key: v })} label="Bahan utama (menu otomatis habis bila stok 0)" />
          </div>
        )}
      </Modal>
    </div>
  );
}

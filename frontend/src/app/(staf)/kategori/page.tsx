"use client";

import { Pencil, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Select, Switch } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet } from "@/hooks/useApi";
import type { Category, Station } from "@/types";

export default function KategoriPage() {
  const list = useGet<Category[]>(["categories"], "/categories");
  const stations = useGet<Station[]>(["stations"], "/stations");
  const [edit, setEdit] = useState<Partial<Category> | null>(null);
  const save = useApiMutation<Partial<Category>>(edit?.id ? "put" : "post", (v) => (v.id ? `/categories/${v.id}` : "/categories"), [["categories"]], () => setEdit(null));
  const del = useApiMutation<{ id: number }>("delete", (v) => `/categories/${v.id}`, [["categories"]]);

  const columns: Column<Category>[] = [
    { header: "Urutan", cell: (c) => c.sort, className: "w-20" },
    { header: "Nama", cell: (c) => <b>{c.name}</b> },
    { header: "Stasiun", cell: (c) => c.station?.name ?? "-" },
    { header: "Menu", cell: (c) => c.menus_count },
    { header: "Status", cell: (c) => <Badge className={c.is_active ? "bg-success-soft text-success" : "bg-surface-3 text-muted"}>{c.is_active ? "Aktif" : "Nonaktif"}</Badge> },
    {
      header: "",
      className: "text-right",
      cell: (c) => (
        <div className="flex justify-end gap-1">
          <Button size="sm" variant="ghost" onClick={() => setEdit(c)} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button>
          <Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus kategori ${c.name}?`, danger: true }); if (r.ok) del.mutate({ id: c.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button>
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Kategori Menu" description="Setiap kategori dipetakan ke stasiun (Dapur/Bar) agar item terdistribusi otomatis di layar KDS." actions={<Button onClick={() => setEdit({ is_active: true, sort: (list.data?.length ?? 0) })}><Plus className="h-4 w-4" /> Kategori</Button>} />
      <Card><DataTable columns={columns} data={list.data} loading={list.isLoading} /></Card>
      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit?.id ? "Ubah kategori" : "Tambah kategori"} size="sm" footer={<Button loading={save.isPending} onClick={() => edit && save.mutate({ id: edit.id, name: edit.name, station_id: edit.station_id, sort: edit.sort, is_active: edit.is_active } as Partial<Category>)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-3">
            <Field label="Nama *"><Input value={edit.name ?? ""} onChange={(e) => setEdit({ ...edit, name: e.target.value })} /></Field>
            <Field label="Stasiun" hint="Item kategori ini tampil di layar stasiun tersebut.">
              <Select value={edit.station_id ?? ""} onChange={(e) => setEdit({ ...edit, station_id: e.target.value ? Number(e.target.value) : null })}>
                <option value="">— Tidak ada —</option>
                {stations.data?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </Select>
            </Field>
            <Field label="Urutan tampil"><Input type="number" min={0} value={edit.sort ?? 0} onChange={(e) => setEdit({ ...edit, sort: Number(e.target.value) })} /></Field>
            <Switch checked={edit.is_active ?? true} onChange={(v) => setEdit({ ...edit, is_active: v })} label="Aktif" />
          </div>
        )}
      </Modal>
    </div>
  );
}

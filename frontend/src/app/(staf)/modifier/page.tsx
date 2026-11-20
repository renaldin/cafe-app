"use client";

import { Pencil, Plus, Trash2, X } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card, EmptyState, Skeleton } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { Field, Input, MoneyInput, Select, Switch } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet } from "@/hooks/useApi";
import { rp } from "@/lib/format";
import type { Ingredient, ModifierGroup } from "@/types";

type OptForm = { id?: number; name: string; price_delta: number; is_available: boolean; is_default: boolean; recipes: { ingredient_id: number; qty: number }[] };
type GroupForm = { id?: number; name: string; selection: "single" | "multiple"; is_required: boolean; max_select: number; options: OptForm[] };

const blank: GroupForm = { name: "", selection: "single", is_required: false, max_select: 1, options: [{ name: "", price_delta: 0, is_available: true, is_default: true, recipes: [] }] };

export default function ModifierPage() {
  const list = useGet<ModifierGroup[]>(["modifier-groups"], "/modifier-groups");
  const ingredients = useGet<Ingredient[]>(["ingredients-all"], "/ingredients", { all: 1 });
  const [edit, setEdit] = useState<GroupForm | null>(null);
  const save = useApiMutation<GroupForm>(edit?.id ? "put" : "post", (v) => (v.id ? `/modifier-groups/${v.id}` : "/modifier-groups"), [["modifier-groups"]], () => setEdit(null));
  const del = useApiMutation<{ id: number }>("delete", (v) => `/modifier-groups/${v.id}`, [["modifier-groups"]]);

  const toForm = (g: ModifierGroup): GroupForm => ({
    id: g.id, name: g.name, selection: g.selection, is_required: g.is_required, max_select: g.max_select,
    options: g.options.map((o) => ({ id: o.id, name: o.name, price_delta: o.price_delta, is_available: o.is_available, is_default: o.is_default, recipes: (o.recipes ?? []).map((r) => ({ ingredient_id: r.ingredient_id, qty: r.qty })) })),
  });
  const setOpt = (i: number, p: Partial<OptForm>) => edit && setEdit({ ...edit, options: edit.options.map((o, j) => (j === i ? { ...o, ...p } : o)) });

  return (
    <div>
      <PageHeader title="Opsi & Modifier" description="Grup opsi seperti Ukuran, Level Gula, Es, Topping. Pasang ke menu di form menu." actions={<Button onClick={() => setEdit(structuredClone(blank))}><Plus className="h-4 w-4" /> Grup opsi</Button>} />
      {list.isLoading ? <Skeleton className="h-40" /> : !list.data?.length ? <Card><EmptyState title="Belum ada grup opsi" /></Card> : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {list.data.map((g) => (
            <Card key={g.id} className="p-4">
              <div className="mb-2 flex items-start justify-between">
                <div>
                  <p className="font-bold">{g.name}</p>
                  <div className="mt-1 flex gap-1">
                    <Badge className="bg-surface-2 text-muted">{g.selection === "single" ? "Pilih satu" : `Pilih banyak (maks ${g.max_select})`}</Badge>
                    <Badge className={g.is_required ? "bg-primary-soft text-primary" : "bg-surface-2 text-muted"}>{g.is_required ? "Wajib" : "Opsional"}</Badge>
                  </div>
                </div>
                <div className="flex">
                  <Button size="sm" variant="ghost" onClick={() => setEdit(toForm(g))} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button>
                  <Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus grup ${g.name}?`, description: "Grup akan dilepas dari semua menu.", danger: true }); if (r.ok) del.mutate({ id: g.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button>
                </div>
              </div>
              <ul className="text-sm">
                {g.options.map((o) => (
                  <li key={o.id} className="flex justify-between py-0.5">
                    <span className={o.is_available ? "" : "text-muted line-through"}>{o.name}{o.is_default && <span className="ml-1 text-xs text-muted">(default)</span>}</span>
                    <span className="text-muted">{o.price_delta ? `+${rp(o.price_delta)}` : "-"}</span>
                  </li>
                ))}
              </ul>
              <p className="mt-2 text-xs text-muted">Dipakai di {g.menus_count ?? 0} menu</p>
            </Card>
          ))}
        </div>
      )}

      <Modal open={!!edit} onClose={() => setEdit(null)} size="lg" title={edit?.id ? "Ubah grup opsi" : "Grup opsi baru"} footer={<Button loading={save.isPending} disabled={!edit?.name || edit.options.some((o) => !o.name)} onClick={() => edit && save.mutate(edit)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-3">
              <Field label="Nama grup *" className="sm:col-span-3"><Input value={edit.name} onChange={(e) => setEdit({ ...edit, name: e.target.value })} placeholder="Contoh: Ukuran" /></Field>
              <Field label="Jenis pilihan">
                <Select value={edit.selection} onChange={(e) => setEdit({ ...edit, selection: e.target.value as "single" | "multiple" })}>
                  <option value="single">Pilih satu</option>
                  <option value="multiple">Pilih banyak</option>
                </Select>
              </Field>
              {edit.selection === "multiple" && <Field label="Maks pilihan"><Input type="number" min={1} value={edit.max_select} onChange={(e) => setEdit({ ...edit, max_select: Number(e.target.value) })} /></Field>}
              <div className="flex items-end pb-2"><Switch checked={edit.is_required} onChange={(v) => setEdit({ ...edit, is_required: v })} label="Wajib dipilih" /></div>
            </div>
            <div>
              <p className="mb-2 font-semibold">Opsi</p>
              <div className="space-y-3">
                {edit.options.map((o, i) => (
                  <div key={i} className="rounded-xl border border-border p-3">
                    <div className="grid gap-2 sm:grid-cols-[1fr_160px_auto]">
                      <Input value={o.name} onChange={(e) => setOpt(i, { name: e.target.value })} placeholder="Nama opsi" aria-label="Nama opsi" />
                      <MoneyInput value={o.price_delta} onChange={(v) => setOpt(i, { price_delta: v })} placeholder="+harga" aria-label="Tambahan harga" />
                      <Button variant="ghost" size="icon" className="text-danger" disabled={edit.options.length <= 1} onClick={() => setEdit({ ...edit, options: edit.options.filter((_, j) => j !== i) })} aria-label="Hapus opsi"><X className="h-4 w-4" /></Button>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-4">
                      <Switch checked={o.is_available} onChange={(v) => setOpt(i, { is_available: v })} label="Tersedia" />
                      <Switch checked={o.is_default} onChange={(v) => setOpt(i, { is_default: v })} label="Default" />
                    </div>
                    <details className="mt-2">
                      <summary className="cursor-pointer text-xs font-medium text-primary">Bahan tambahan untuk opsi ini ({o.recipes.length})</summary>
                      <div className="mt-2 space-y-1.5">
                        {o.recipes.map((r, k) => (
                          <div key={k} className="flex gap-2">
                            <Select value={r.ingredient_id} onChange={(e) => setOpt(i, { recipes: o.recipes.map((x, y) => (y === k ? { ...x, ingredient_id: Number(e.target.value) } : x)) })} aria-label="Bahan">
                              {ingredients.data?.map((ing) => <option key={ing.id} value={ing.id}>{ing.name} ({ing.unit})</option>)}
                            </Select>
                            <Input type="number" step="0.001" className="w-28" value={r.qty} onChange={(e) => setOpt(i, { recipes: o.recipes.map((x, y) => (y === k ? { ...x, qty: Number(e.target.value) } : x)) })} aria-label="Jumlah" />
                            <Button variant="ghost" size="icon" onClick={() => setOpt(i, { recipes: o.recipes.filter((_, y) => y !== k) })} aria-label="Hapus bahan"><X className="h-4 w-4" /></Button>
                          </div>
                        ))}
                        <Button size="sm" variant="ghost" disabled={!ingredients.data?.length} onClick={() => setOpt(i, { recipes: [...o.recipes, { ingredient_id: ingredients.data![0].id, qty: 0 }] })}><Plus className="h-3.5 w-3.5" /> Bahan</Button>
                        <p className="text-[11px] text-muted">Contoh: ukuran Large +60 ml susu. Nilai negatif untuk mengurangi (mis. No Sugar).</p>
                      </div>
                    </details>
                  </div>
                ))}
              </div>
              <Button variant="ghost" size="sm" className="mt-2" onClick={() => setEdit({ ...edit, options: [...edit.options, { name: "", price_delta: 0, is_available: true, is_default: false, recipes: [] }] })}><Plus className="h-4 w-4" /> Tambah opsi</Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}

"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ImagePlus, Plus, X } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, Skeleton } from "@/components/ui/card";
import { Checkbox, Field, Input, MoneyInput, Select, Switch, Textarea } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { useGet } from "@/hooks/useApi";
import { api, errMsg, fieldErrors } from "@/lib/api";
import { LABELS, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Category, Ingredient, Menu, ModifierGroup, Station } from "@/types";

const schema = z.object({
  name: z.string().min(2, "Nama minimal 2 karakter").max(150),
  category_id: z.coerce.number<number>().min(1, "Pilih kategori"),
  station_id: z.coerce.number<number>().nullable().optional(),
  sku: z.string().max(50).optional().nullable(),
  description: z.string().max(1000).optional().nullable(),
  price: z.number().min(0, "Harga tidak valid"),
  labels: z.array(z.string()),
  is_available: z.boolean(),
  is_active: z.boolean(),
  is_bundle: z.boolean(),
  available_from: z.string().optional().nullable(),
  available_until: z.string().optional().nullable(),
  sort: z.coerce.number<number>().min(0),
  modifier_group_ids: z.array(z.number()),
});
type Form = z.infer<typeof schema>;

function MenuForm() {
  const id = useSearchParams().get("id");
  const router = useRouter();
  const can = useCan();
  const qc = useQueryClient();
  const cats = useGet<Category[]>(["categories"], "/categories");
  const stations = useGet<Station[]>(["stations"], "/stations");
  const groups = useGet<ModifierGroup[]>(["modifier-groups"], "/modifier-groups");
  const ingredients = useGet<Ingredient[]>(["ingredients-all"], "/ingredients", { all: 1 }, { enabled: can("stock.view") });
  const allMenus = useGet<Menu[]>(["menus-all"], "/menus", { all: 1 });
  const menu = useQuery({ queryKey: ["menu", id], enabled: !!id, queryFn: async () => (await api.get<{ data: Menu }>(`/menus/${id}`)).data.data });

  const { register, handleSubmit, control, reset, watch, setError, formState } = useForm<Form>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", category_id: 0, price: 0, labels: [], is_available: true, is_active: true, is_bundle: false, sort: 0, modifier_group_ids: [], station_id: null, sku: "", description: "", available_from: "", available_until: "" },
  });
  const [recipe, setRecipe] = useState<{ ingredient_id: number; qty: number }[]>([]);
  const [bundle, setBundle] = useState<{ menu_id: number; qty: number }[]>([]);
  const [image, setImage] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);

  useEffect(() => {
    const m = menu.data;
    if (!m) return;
    reset({
      name: m.name, category_id: m.category_id, station_id: m.station_id, sku: m.sku ?? "", description: m.description ?? "", price: m.price,
      labels: m.labels, is_available: m.is_available, is_active: m.is_active, is_bundle: m.is_bundle, available_from: m.available_from ?? "",
      available_until: m.available_until ?? "", sort: m.sort, modifier_group_ids: (m.modifier_groups ?? []).map((g) => g.id),
    });
    setRecipe((m.recipes ?? []).map((r) => ({ ingredient_id: r.ingredient_id, qty: r.qty })));
    setBundle((m.bundle_items ?? []).map((b) => ({ menu_id: b.menu_id, qty: b.qty })));
    setPreview(m.image_url);
  }, [menu.data, reset]);

  const isBundle = watch("is_bundle");
  const price = watch("price");
  const costMap = new Map((ingredients.data ?? []).map((i) => [i.id, i.cost_per_unit]));
  const hpp = Math.round(recipe.reduce((s, r) => s + r.qty * (costMap.get(r.ingredient_id) ?? 0), 0));

  const save = useMutation({
    mutationFn: async (v: Form) => {
      const body = { ...v, station_id: v.station_id || null, available_from: v.available_from || null, available_until: v.available_until || null, sku: v.sku || null, bundle_items: v.is_bundle ? bundle : [] };
      const res = id ? await api.put(`/menus/${id}`, body) : await api.post("/menus", body);
      const saved = res.data.data as Menu;
      if (image) {
        const fd = new FormData();
        fd.append("image", image);
        await api.post(`/menus/${saved.id}/image`, fd);
      }
      if (can("recipe.manage")) await api.put(`/menus/${saved.id}/recipe`, { items: recipe.filter((r) => r.qty > 0) });
      return saved;
    },
    onSuccess: () => {
      toast.success("Menu tersimpan.");
      void qc.invalidateQueries({ queryKey: ["menus"] });
      void qc.invalidateQueries({ queryKey: ["menus-all"] });
      router.push("/menu/");
    },
    onError: (e) => {
      Object.entries(fieldErrors(e)).forEach(([k, m]) => setError(k as keyof Form, { message: m }));
      toast.error(errMsg(e));
    },
  });

  if (id && menu.isLoading) return <Skeleton className="h-96" />;

  return (
    <form onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
      <PageHeader title={id ? `Ubah menu` : "Menu baru"} actions={<><Button variant="outline" onClick={() => router.back()}>Batal</Button><Button type="submit" loading={save.isPending}>Simpan menu</Button></>} />
      <div className="grid gap-4 lg:grid-cols-[1fr_360px]">
        <div className="space-y-4">
          <Card>
            <CardHeader title="Informasi menu" />
            <div className="grid gap-4 p-5 sm:grid-cols-2">
              <Field label="Nama menu *" error={formState.errors.name?.message} className="sm:col-span-2"><Input {...register("name")} /></Field>
              <Field label="Kategori *" error={formState.errors.category_id?.message}>
                <Select {...register("category_id")}>
                  <option value={0}>— Pilih —</option>
                  {cats.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </Select>
              </Field>
              <Field label="Harga *" error={formState.errors.price?.message}>
                <Controller control={control} name="price" render={({ field }) => <MoneyInput value={field.value} onChange={field.onChange} />} />
              </Field>
              <Field label="SKU" error={formState.errors.sku?.message}><Input {...register("sku")} /></Field>
              <Field label="Stasiun (override kategori)">
                <Select {...register("station_id")}>
                  <option value="">Ikuti kategori</option>
                  {stations.data?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </Select>
              </Field>
              <Field label="Deskripsi" className="sm:col-span-2"><Textarea {...register("description")} /></Field>
              <Field label="Tersedia mulai" hint="Kosongkan bila sepanjang hari"><Input type="time" {...register("available_from")} /></Field>
              <Field label="Tersedia sampai"><Input type="time" {...register("available_until")} /></Field>
              <Field label="Urutan tampil"><Input type="number" min={0} {...register("sort")} /></Field>
            </div>
          </Card>

          <Card>
            <CardHeader title="Opsi / modifier" description="Grup opsi yang bisa dipilih pelanggan." />
            <div className="grid gap-2 p-5 sm:grid-cols-2">
              <Controller
                control={control}
                name="modifier_group_ids"
                render={({ field }) => (
                  <>
                    {groups.data?.map((g) => (
                      <div key={g.id} className="rounded-xl border border-border px-3 py-2">
                        <Checkbox checked={field.value.includes(g.id)} onChange={(v) => field.onChange(v ? [...field.value, g.id] : field.value.filter((x) => x !== g.id))} label={<span><b>{g.name}</b> <span className="text-xs text-muted">({g.options.map((o) => o.name).join(", ")})</span></span>} />
                      </div>
                    ))}
                  </>
                )}
              />
            </div>
          </Card>

          {can("recipe.manage") && (
            <Card>
              <CardHeader title="Resep (komposisi per porsi)" description="Stok bahan berkurang otomatis sesuai resep. Dipakai juga untuk menghitung HPP." action={<span className="text-sm">HPP: <b>{rp(hpp)}</b>{price > 0 && <span className="text-muted"> · margin {Math.round(((price - hpp) / price) * 100)}%</span>}</span>} />
              <div className="space-y-2 p-5">
                {recipe.map((r, i) => {
                  const ing = ingredients.data?.find((x) => x.id === r.ingredient_id);
                  return (
                    <div key={i} className="flex items-center gap-2">
                      <Select value={r.ingredient_id} onChange={(e) => setRecipe(recipe.map((x, j) => (j === i ? { ...x, ingredient_id: Number(e.target.value) } : x)))} aria-label="Bahan">
                        {ingredients.data?.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
                      </Select>
                      <Input type="number" step="0.001" min={0} className="w-28" value={r.qty} onChange={(e) => setRecipe(recipe.map((x, j) => (j === i ? { ...x, qty: Number(e.target.value) } : x)))} aria-label="Jumlah" />
                      <span className="w-10 text-sm text-muted">{ing?.unit}</span>
                      <span className="hidden w-24 text-right text-sm text-muted sm:block">{rp(r.qty * (ing?.cost_per_unit ?? 0))}</span>
                      <Button variant="ghost" size="icon" onClick={() => setRecipe(recipe.filter((_, j) => j !== i))} aria-label="Hapus bahan"><X className="h-4 w-4" /></Button>
                    </div>
                  );
                })}
                <Button variant="ghost" size="sm" disabled={!ingredients.data?.length} onClick={() => setRecipe([...recipe, { ingredient_id: ingredients.data![0].id, qty: 0 }])}><Plus className="h-4 w-4" /> Tambah bahan</Button>
              </div>
            </Card>
          )}

          {isBundle && (
            <Card>
              <CardHeader title="Isi paket" />
              <div className="space-y-2 p-5">
                {bundle.map((b, i) => (
                  <div key={i} className="flex gap-2">
                    <Select value={b.menu_id} onChange={(e) => setBundle(bundle.map((x, j) => (j === i ? { ...x, menu_id: Number(e.target.value) } : x)))} aria-label="Menu isi paket">
                      {allMenus.data?.filter((m) => !m.is_bundle).map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                    </Select>
                    <Input type="number" min={1} className="w-20" value={b.qty} onChange={(e) => setBundle(bundle.map((x, j) => (j === i ? { ...x, qty: Number(e.target.value) } : x)))} aria-label="Jumlah" />
                    <Button variant="ghost" size="icon" onClick={() => setBundle(bundle.filter((_, j) => j !== i))} aria-label="Hapus"><X className="h-4 w-4" /></Button>
                  </div>
                ))}
                <Button variant="ghost" size="sm" onClick={() => setBundle([...bundle, { menu_id: allMenus.data?.[0]?.id ?? 0, qty: 1 }])}><Plus className="h-4 w-4" /> Tambah isi</Button>
              </div>
            </Card>
          )}
        </div>

        <div className="space-y-4">
          <Card className="p-5">
            <p className="mb-2 font-semibold">Foto</p>
            <label className="flex aspect-square cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-border bg-surface-2 text-muted hover:border-primary">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {preview ? <img src={preview} alt="Pratinjau" className="h-full w-full object-cover" /> : <><ImagePlus className="h-8 w-8" /><span className="mt-1 text-sm">Pilih foto (maks 5 MB)</span></>}
              <input type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={(e) => { const f = e.target.files?.[0]; if (f) { setImage(f); setPreview(URL.createObjectURL(f)); } }} />
            </label>
            <p className="mt-2 text-xs text-muted">Foto dikompres otomatis (WebP, maks 800px) agar halaman pelanggan tetap ringan.</p>
          </Card>
          <Card className="space-y-3 p-5">
            <Controller control={control} name="is_available" render={({ field }) => <Switch checked={field.value} onChange={field.onChange} label="Tersedia (tidak habis)" />} />
            <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onChange={field.onChange} label="Tampil di menu" />} />
            <Controller control={control} name="is_bundle" render={({ field }) => <Switch checked={field.value} onChange={field.onChange} label="Paket / bundle" />} />
          </Card>
          <Card className="p-5">
            <p className="mb-2 font-semibold">Label</p>
            <Controller
              control={control}
              name="labels"
              render={({ field }) => (
                <div className="space-y-2">
                  {Object.entries(LABELS).map(([k, v]) => <Checkbox key={k} checked={field.value.includes(k)} onChange={(c) => field.onChange(c ? [...field.value, k] : field.value.filter((x) => x !== k))} label={v.text} />)}
                </div>
              )}
            />
          </Card>
        </div>
      </div>
    </form>
  );
}

export default function MenuFormPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96" />}>
      <MenuForm />
    </Suspense>
  );
}

"use client";

import { Pencil, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card, CardHeader } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Checkbox, Field, Input, MoneyInput, Select, Switch } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { useApiMutation, useGet, usePaged } from "@/hooks/useApi";
import { fdate, rp } from "@/lib/format";
import type { Category } from "@/types";

type Voucher = { id: number; code: string; name: string; type: "percent" | "fixed"; value: number; max_discount: number | null; min_spend: number; starts_at: string | null; ends_at: string | null; usage_limit: number | null; used_count: number; per_customer_limit: number | null; is_active: boolean };
type HappyHour = { id: number; name: string; days: number[]; start_time: string; end_time: string; type: "percent" | "fixed"; value: number; category_ids: number[] | null; is_active: boolean };
const DAYS = ["", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"];

const discText = (t: string, v: number) => (t === "percent" ? `${v}%` : rp(v));

export default function PromoPage() {
  const [tab, setTab] = useState<"voucher" | "hh">("voucher");
  const [page, setPage] = useState(1);
  const vouchers = usePaged<Voucher>(["vouchers"], "/vouchers", { page });
  const hh = useGet<HappyHour[]>(["happy-hours"], "/happy-hours");
  const cats = useGet<Category[]>(["categories"], "/categories");
  const [v, setV] = useState<Partial<Voucher> | null>(null);
  const [h, setH] = useState<Partial<HappyHour> | null>(null);
  const saveV = useApiMutation<Partial<Voucher>>(v?.id ? "put" : "post", (x) => (x.id ? `/vouchers/${x.id}` : "/vouchers"), [["vouchers"]], () => setV(null));
  const delV = useApiMutation<{ id: number }>("delete", (x) => `/vouchers/${x.id}`, [["vouchers"]]);
  const saveH = useApiMutation<Partial<HappyHour>>(h?.id ? "put" : "post", (x) => (x.id ? `/happy-hours/${x.id}` : "/happy-hours"), [["happy-hours"]], () => setH(null));
  const delH = useApiMutation<{ id: number }>("delete", (x) => `/happy-hours/${x.id}`, [["happy-hours"]]);

  const vcols: Column<Voucher>[] = [
    { header: "Kode", cell: (x) => <b className="font-mono">{x.code}</b> },
    { header: "Nama", cell: (x) => x.name },
    { header: "Diskon", cell: (x) => `${discText(x.type, x.value)}${x.max_discount ? ` (maks ${rp(x.max_discount)})` : ""}` },
    { header: "Min. belanja", cell: (x) => rp(x.min_spend) },
    { header: "Periode", cell: (x) => (x.starts_at || x.ends_at ? `${fdate(x.starts_at)} – ${fdate(x.ends_at)}` : "Tanpa batas") },
    { header: "Pemakaian", cell: (x) => `${x.used_count}${x.usage_limit ? ` / ${x.usage_limit}` : ""}` },
    { header: "Status", cell: (x) => <Badge className={x.is_active ? "bg-success-soft text-success" : "bg-surface-3 text-muted"}>{x.is_active ? "Aktif" : "Nonaktif"}</Badge> },
    { header: "", className: "text-right", cell: (x) => <div className="flex justify-end gap-1"><Button size="sm" variant="ghost" onClick={() => setV({ ...x, starts_at: x.starts_at?.slice(0, 16) ?? null, ends_at: x.ends_at?.slice(0, 16) ?? null })} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button><Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus voucher ${x.code}?`, danger: true }); if (r.ok) delV.mutate({ id: x.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button></div> },
  ];

  return (
    <div>
      <PageHeader title="Voucher & Promo" actions={tab === "voucher" ? <Button onClick={() => setV({ type: "percent", value: 10, min_spend: 0, is_active: true })}><Plus className="h-4 w-4" /> Voucher</Button> : <Button onClick={() => setH({ days: [1, 2, 3, 4, 5], start_time: "14:00", end_time: "16:00", type: "percent", value: 10, is_active: true, category_ids: [] })}><Plus className="h-4 w-4" /> Happy hour</Button>} />
      <Tabs className="mb-4" value={tab} onChange={setTab} items={[{ value: "voucher", label: "Kode voucher" }, { value: "hh", label: "Happy hour" }]} />
      {tab === "voucher" ? (
        <Card><DataTable columns={vcols} data={vouchers.data?.data} loading={vouchers.isLoading} meta={vouchers.data?.meta} onPage={setPage} /></Card>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {hh.data?.map((x) => (
            <Card key={x.id}>
              <CardHeader title={x.name} description={`${x.days.map((d) => DAYS[d]).join(", ")} · ${x.start_time.slice(0, 5)}–${x.end_time.slice(0, 5)}`} action={<Badge className={x.is_active ? "bg-success-soft text-success" : "bg-surface-3 text-muted"}>{x.is_active ? "Aktif" : "Nonaktif"}</Badge>} />
              <div className="flex items-center justify-between p-5">
                <div>
                  <p className="text-2xl font-black text-primary">{discText(x.type, x.value)}</p>
                  <p className="text-xs text-muted">{x.category_ids?.length ? cats.data?.filter((c) => x.category_ids!.includes(c.id)).map((c) => c.name).join(", ") : "Semua kategori"}</p>
                </div>
                <div className="flex gap-1">
                  <Button size="sm" variant="ghost" onClick={() => setH({ ...x, start_time: x.start_time.slice(0, 5), end_time: x.end_time.slice(0, 5) })} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button>
                  <Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus ${x.name}?`, danger: true }); if (r.ok) delH.mutate({ id: x.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={!!v} onClose={() => setV(null)} title={v?.id ? "Ubah voucher" : "Voucher baru"} footer={<Button loading={saveV.isPending} onClick={() => v && saveV.mutate(v)}>Simpan</Button>}>
        {v && (
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Kode *"><Input className="font-mono uppercase" value={v.code ?? ""} onChange={(e) => setV({ ...v, code: e.target.value.toUpperCase() })} /></Field>
            <Field label="Nama *"><Input value={v.name ?? ""} onChange={(e) => setV({ ...v, name: e.target.value })} /></Field>
            <Field label="Jenis">
              <Select value={v.type} onChange={(e) => setV({ ...v, type: e.target.value as "percent" | "fixed" })}><option value="percent">Persen</option><option value="fixed">Nominal</option></Select>
            </Field>
            <Field label={v.type === "percent" ? "Diskon (%)" : "Diskon (Rp)"}>{v.type === "percent" ? <Input type="number" min={1} max={100} value={v.value ?? ""} onChange={(e) => setV({ ...v, value: Number(e.target.value) })} /> : <MoneyInput value={v.value ?? 0} onChange={(x) => setV({ ...v, value: x })} />}</Field>
            {v.type === "percent" && <Field label="Maks potongan"><MoneyInput value={v.max_discount ?? 0} onChange={(x) => setV({ ...v, max_discount: x || null })} /></Field>}
            <Field label="Minimal belanja"><MoneyInput value={v.min_spend ?? 0} onChange={(x) => setV({ ...v, min_spend: x })} /></Field>
            <Field label="Berlaku mulai"><Input type="datetime-local" value={v.starts_at ?? ""} onChange={(e) => setV({ ...v, starts_at: e.target.value || null })} /></Field>
            <Field label="Berlaku sampai"><Input type="datetime-local" value={v.ends_at ?? ""} onChange={(e) => setV({ ...v, ends_at: e.target.value || null })} /></Field>
            <Field label="Batas pemakaian total"><Input type="number" min={1} value={v.usage_limit ?? ""} onChange={(e) => setV({ ...v, usage_limit: e.target.value ? Number(e.target.value) : null })} placeholder="Tanpa batas" /></Field>
            <Field label="Batas per pelanggan"><Input type="number" min={1} value={v.per_customer_limit ?? ""} onChange={(e) => setV({ ...v, per_customer_limit: e.target.value ? Number(e.target.value) : null })} placeholder="Tanpa batas" /></Field>
            <Switch checked={v.is_active ?? true} onChange={(x) => setV({ ...v, is_active: x })} label="Aktif" />
          </div>
        )}
      </Modal>

      <Modal open={!!h} onClose={() => setH(null)} title={h?.id ? "Ubah happy hour" : "Happy hour baru"} description="Diskon otomatis untuk item yang dipesan pada jam tersebut." footer={<Button loading={saveH.isPending} onClick={() => h && saveH.mutate({ ...h, category_ids: h.category_ids?.length ? h.category_ids : null })}>Simpan</Button>}>
        {h && (
          <div className="space-y-3">
            <Field label="Nama *"><Input value={h.name ?? ""} onChange={(e) => setH({ ...h, name: e.target.value })} /></Field>
            <div>
              <p className="mb-1.5 text-sm font-medium">Hari</p>
              <div className="flex flex-wrap gap-1.5">
                {[1, 2, 3, 4, 5, 6, 7].map((d) => (
                  <button key={d} onClick={() => setH({ ...h, days: h.days?.includes(d) ? h.days.filter((x) => x !== d) : [...(h.days ?? []), d] })} className={`h-9 w-12 rounded-lg border text-sm font-semibold ${h.days?.includes(d) ? "border-primary bg-primary text-primary-fg" : "border-border"}`}>{DAYS[d]}</button>
                ))}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <Field label="Mulai"><Input type="time" value={h.start_time ?? ""} onChange={(e) => setH({ ...h, start_time: e.target.value })} /></Field>
              <Field label="Selesai"><Input type="time" value={h.end_time ?? ""} onChange={(e) => setH({ ...h, end_time: e.target.value })} /></Field>
              <Field label="Jenis"><Select value={h.type} onChange={(e) => setH({ ...h, type: e.target.value as "percent" | "fixed" })}><option value="percent">Persen</option><option value="fixed">Nominal per item</option></Select></Field>
              <Field label="Nilai"><Input type="number" min={1} value={h.value ?? ""} onChange={(e) => setH({ ...h, value: Number(e.target.value) })} /></Field>
            </div>
            <div>
              <p className="mb-1.5 text-sm font-medium">Kategori (kosong = semua)</p>
              <div className="grid grid-cols-2 gap-1.5">
                {cats.data?.map((c) => <Checkbox key={c.id} checked={!!h.category_ids?.includes(c.id)} onChange={(x) => setH({ ...h, category_ids: x ? [...(h.category_ids ?? []), c.id] : (h.category_ids ?? []).filter((y) => y !== c.id) })} label={c.name} />)}
              </div>
            </div>
            <Switch checked={h.is_active ?? true} onChange={(x) => setH({ ...h, is_active: x })} label="Aktif" />
          </div>
        )}
      </Modal>
    </div>
  );
}

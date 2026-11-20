"use client";

import { useQueryClient } from "@tanstack/react-query";
import { Brush, Pencil, Plus, Printer, QrCode, RefreshCw, Settings2, Trash2, Users } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import QRCode from "qrcode";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card, EmptyState, Skeleton } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { Field, Input, Select, Switch } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet } from "@/hooks/useApi";
import { usePolling } from "@/hooks/usePolling";
import { APP_URL, api } from "@/lib/api";
import { cn } from "@/lib/cn";
import { TABLE_STATUS, ago, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Area, TableRow } from "@/types";

function tableUrl(token?: string) {
  return `${APP_URL}/pesan/?t=${token ?? ""}`;
}

function QrPreview({ token }: { token?: string }) {
  const [src, setSrc] = useState("");
  useEffect(() => {
    if (token) void QRCode.toDataURL(tableUrl(token), { width: 360, margin: 1 }).then(setSrc);
  }, [token]);
  return src ? (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt="QR meja" className="mx-auto h-48 w-48 rounded-xl bg-white p-2" />
  ) : (
    <Skeleton className="mx-auto h-48 w-48" />
  );
}

export default function MejaPage() {
  const can = useCan();
  const router = useRouter();
  const qc = useQueryClient();
  const q = usePolling<TableRow[]>(api, ["tables"], "/tables", { interval: 8000 });
  const areas = useGet<Area[]>(["areas"], "/areas");
  const [selected, setSelected] = useState<TableRow | null>(null);
  const [edit, setEdit] = useState<Partial<TableRow> | null>(null);
  const [areaModal, setAreaModal] = useState(false);
  const [areaName, setAreaName] = useState("");
  const refresh = () => void qc.invalidateQueries({ queryKey: ["tables"] });

  const status = useApiMutation<{ id: number; status: string }>("patch", (v) => `/tables/${v.id}/status`, [["tables"]], () => setSelected(null));
  const regen = useApiMutation<{ id: number }>("post", (v) => `/tables/${v.id}/regenerate-token`, [["tables"]], (d) => setSelected(d as TableRow));
  const save = useApiMutation<Partial<TableRow>>(edit?.id ? "put" : "post", (v) => (v.id ? `/tables/${v.id}` : "/tables"), [["tables"]], () => setEdit(null));
  const del = useApiMutation<{ id: number }>("delete", (v) => `/tables/${v.id}`, [["tables"]], () => setSelected(null));
  const addArea = useApiMutation<{ name: string }>("post", "/areas", [["areas"]], () => setAreaName(""));
  const delArea = useApiMutation<{ id: number }>("delete", (v) => `/areas/${v.id}`, [["areas"]]);

  const tables = Array.isArray(q.data) ? q.data : [];
  const grouped = (areas.data ?? []).map((a) => ({ area: a, tables: tables.filter((t) => t.area_id === a.id) }));
  const noArea = tables.filter((t) => !t.area_id);
  if (noArea.length) grouped.push({ area: { id: 0, name: "Tanpa area", sort: 99 }, tables: noArea });
  const counts = Object.keys(TABLE_STATUS).reduce((acc, k) => ({ ...acc, [k]: tables.filter((t) => t.status === k).length }), {} as Record<string, number>);

  return (
    <div>
      <PageHeader
        title="Denah Meja"
        description="Status meja diperbarui otomatis."
        actions={
          <>
            <Button variant="outline" size="sm" onClick={refresh}><RefreshCw className="h-4 w-4" /></Button>
            {can("table.view") && <Link href="/qr-cetak/" target="_blank"><Button variant="outline" size="sm"><Printer className="h-4 w-4" /> Cetak QR</Button></Link>}
            {can("table.manage") && <Button variant="outline" size="sm" onClick={() => setAreaModal(true)}><Settings2 className="h-4 w-4" /> Area</Button>}
            {can("table.manage") && <Button size="sm" onClick={() => setEdit({ capacity: 4, is_active: true, area_id: areas.data?.[0]?.id ?? null })}><Plus className="h-4 w-4" /> Meja</Button>}
          </>
        }
      />
      <div className="mb-4 flex flex-wrap gap-3 text-sm">
        {Object.entries(TABLE_STATUS).map(([k, v]) => (
          <span key={k} className="flex items-center gap-1.5"><span className={cn("h-2.5 w-2.5 rounded-full", v.dot)} /> {v.text} <b>{counts[k] ?? 0}</b></span>
        ))}
      </div>

      {q.isLoading && <div className="grid grid-cols-3 gap-3 md:grid-cols-6">{Array.from({ length: 10 }).map((_, i) => <Skeleton key={i} className="h-28" />)}</div>}
      {!q.isLoading && tables.length === 0 && <Card><EmptyState title="Belum ada meja" description="Tambahkan meja untuk mulai menerima pesanan via QR." /></Card>}

      <div className="space-y-6">
        {grouped.filter((g) => g.tables.length).map(({ area, tables: list }) => (
          <section key={area.id}>
            <h2 className="mb-2 font-semibold text-muted">{area.name}</h2>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
              {list.map((t) => (
                <button key={t.id} onClick={() => setSelected(t)} className={cn("card-shadow relative flex aspect-[4/3] flex-col justify-between rounded-2xl border-2 p-3 text-left transition hover:-translate-y-0.5", TABLE_STATUS[t.status]?.className, !t.is_active && "opacity-50")}>
                  <div className="flex items-start justify-between">
                    <span className="text-2xl font-black">{t.code}</span>
                    <span className={cn("mt-1.5 h-3 w-3 rounded-full", TABLE_STATUS[t.status]?.dot)} />
                  </div>
                  <div>
                    <p className="text-xs font-medium">{t.status_label}</p>
                    {t.open_bill ? (
                      <p className="text-sm font-bold">{rp(t.open_bill.total)} <span className="text-[11px] font-normal text-muted">· {ago(t.open_bill.opened_at)}</span></p>
                    ) : (
                      <p className="flex items-center gap-1 text-xs text-muted"><Users className="h-3 w-3" /> {t.capacity} kursi</p>
                    )}
                  </div>
                </button>
              ))}
            </div>
          </section>
        ))}
      </div>

      {/* Detail meja */}
      <Modal open={!!selected} onClose={() => setSelected(null)} title={selected ? `${selected.name}` : ""} description={selected ? `${selected.area?.name ?? ""} · ${selected.capacity} kursi` : ""} size="sm">
        {selected && (
          <div className="space-y-4">
            <Badge className={cn("text-sm", TABLE_STATUS[selected.status]?.className)}>{selected.status_label}</Badge>
            {selected.open_bill && (
              <div className="rounded-xl bg-surface-2 p-3">
                <p className="text-sm text-muted">{selected.open_bill.invoice_no}</p>
                <p className="text-xl font-bold">{rp(selected.open_bill.total)}</p>
              </div>
            )}
            <div className="grid gap-2">
              {selected.open_bill && can("bill.view") && <Button onClick={() => router.push(`/kasir/bill/?id=${selected.open_bill!.id}`)}>Buka bill</Button>}
              {can("order.create") && <Button variant="outline" onClick={() => router.push(`/pesanan/baru/?table=${selected.id}`)}><Plus className="h-4 w-4" /> {selected.open_bill ? "Tambah pesanan" : "Pesanan baru"}</Button>}
              {selected.status === "needs_cleaning" && can("table.status") && <Button variant="success" onClick={() => status.mutate({ id: selected.id, status: "empty" })} loading={status.isPending}><Brush className="h-4 w-4" /> Sudah dibersihkan</Button>}
            </div>
            {selected.qr_token && (
              <div className="rounded-2xl border border-border p-3 text-center">
                <p className="mb-2 flex items-center justify-center gap-1 text-sm font-semibold"><QrCode className="h-4 w-4" /> QR pemesanan</p>
                <QrPreview token={selected.qr_token} />
                <p className="mt-2 break-all text-[11px] text-muted">{tableUrl(selected.qr_token)}</p>
                <div className="mt-3 flex justify-center gap-2">
                  <Link href={`/qr-cetak/?ids=${selected.id}`} target="_blank"><Button size="sm" variant="outline"><Printer className="h-4 w-4" /> Cetak</Button></Link>
                  {can("table.regenerate_qr") && (
                    <Button size="sm" variant="outline" onClick={async () => { const r = await confirm({ title: "Ganti QR meja ini?", description: "QR lama tidak bisa dipakai lagi. Anda perlu mencetak & memasang QR baru.", danger: true, confirmText: "Ganti QR" }); if (r.ok) regen.mutate({ id: selected.id }); }}>
                      <RefreshCw className="h-4 w-4" /> Ganti QR
                    </Button>
                  )}
                </div>
              </div>
            )}
            {can("table.manage") && (
              <div className="flex gap-2 border-t border-border pt-3">
                <Button variant="ghost" size="sm" onClick={() => { setEdit(selected); setSelected(null); }}><Pencil className="h-4 w-4" /> Ubah</Button>
                <Button variant="ghost" size="sm" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus meja ${selected.code}?`, danger: true }); if (r.ok) del.mutate({ id: selected.id }); }}><Trash2 className="h-4 w-4" /> Hapus</Button>
              </div>
            )}
          </div>
        )}
      </Modal>

      {/* Form meja */}
      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit?.id ? "Ubah meja" : "Tambah meja"} size="sm" footer={<Button loading={save.isPending} onClick={() => edit && save.mutate({ id: edit.id, code: edit.code, name: edit.name, area_id: edit.area_id, capacity: edit.capacity, is_active: edit.is_active } as Partial<TableRow>)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2">
              <Field label="Kode *"><Input value={edit.code ?? ""} onChange={(e) => setEdit({ ...edit, code: e.target.value.toUpperCase() })} placeholder="A1" /></Field>
              <Field label="Kapasitas"><Input type="number" min={1} value={edit.capacity ?? 4} onChange={(e) => setEdit({ ...edit, capacity: Number(e.target.value) })} /></Field>
            </div>
            <Field label="Nama"><Input value={edit.name ?? ""} onChange={(e) => setEdit({ ...edit, name: e.target.value })} placeholder="Meja A1" /></Field>
            <Field label="Area">
              <Select value={edit.area_id ?? ""} onChange={(e) => setEdit({ ...edit, area_id: e.target.value ? Number(e.target.value) : null })}>
                <option value="">Tanpa area</option>
                {areas.data?.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
              </Select>
            </Field>
            <Switch checked={edit.is_active ?? true} onChange={(v) => setEdit({ ...edit, is_active: v })} label="Aktif" />
          </div>
        )}
      </Modal>

      <Modal open={areaModal} onClose={() => setAreaModal(false)} title="Kelola area" size="sm">
        <ul className="mb-3 divide-y divide-border">
          {areas.data?.map((a) => (
            <li key={a.id} className="flex items-center justify-between py-2">
              <span>{a.name} <span className="text-xs text-muted">({a.tables_count} meja)</span></span>
              <button className="p-1 text-danger" onClick={() => delArea.mutate({ id: a.id })} aria-label={`Hapus area ${a.name}`}><Trash2 className="h-4 w-4" /></button>
            </li>
          ))}
        </ul>
        <div className="flex gap-2">
          <Input value={areaName} onChange={(e) => setAreaName(e.target.value)} placeholder="Nama area baru" />
          <Button disabled={!areaName} loading={addArea.isPending} onClick={() => addArea.mutate({ name: areaName })}>Tambah</Button>
        </div>
      </Modal>
    </div>
  );
}

"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Database, ImagePlus, Save } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, Skeleton } from "@/components/ui/card";
import { Field, Input, Select, Switch, Textarea } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { APP_URL, api, errMsg } from "@/lib/api";

type S = Record<string, unknown> & { opening_hours: Record<string, { open: string; close: string; closed: boolean }> };
type Meta = { gateway: string | null; webhook_url: string | null; timezone: string; php_version: string };
const DAYS = ["", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"];

export default function PengaturanPage() {
  const qc = useQueryClient();
  const q = useQuery({ queryKey: ["settings"], queryFn: async () => (await api.get<{ data: S; meta: Meta }>("/settings")).data });
  // draft lokal; bila belum diubah, pakai data server
  const [draft, setS] = useState<S | null>(null);
  const [tab, setTab] = useState<"profil" | "transaksi" | "operasional" | "sistem">("profil");
  const [saving, setSaving] = useState(false);
  const s = draft ?? q.data?.data ?? null;
  if (!s) return <Skeleton className="h-96" />;
  const set = (k: string, v: unknown) => setS({ ...s, [k]: v });
  const str = (k: string) => (s[k] as string | null) ?? "";
  const bool = (k: string) => Boolean(s[k]);

  const save = async () => {
    setSaving(true);
    try {
      const omit = ["cafe_logo", "cafe_logo_url", "payment_qris_image", "payment_qris_image_url"];
      const body = Object.fromEntries(Object.entries(s).filter(([k]) => !omit.includes(k)));
      await api.put("/settings", body);
      toast.success("Pengaturan disimpan.");
      await qc.invalidateQueries({ queryKey: ["settings"] });
      setS(null);
    } catch (e) {
      toast.error(errMsg(e));
    } finally {
      setSaving(false);
    }
  };
  const upload = async (key: string, f: File) => {
    const fd = new FormData();
    fd.append("key", key);
    fd.append("image", f);
    try {
      const res = await api.post("/settings/upload", fd);
      toast.success("Gambar diperbarui.");
      setS((d) => (d ? { ...d, [`${key}_url`]: res.data.data.url } : d));
      void qc.invalidateQueries({ queryKey: ["settings"] });
    } catch (e) {
      toast.error(errMsg(e));
    }
  };

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader title="Pengaturan" actions={<Button loading={saving} onClick={save}><Save className="h-4 w-4" /> Simpan</Button>} />
      <Tabs className="mb-4" value={tab} onChange={setTab} items={[{ value: "profil", label: "Profil cafe" }, { value: "transaksi", label: "Pajak & struk" }, { value: "operasional", label: "Operasional" }, { value: "sistem", label: "Sistem & backup" }]} />

      {tab === "profil" && (
        <div className="space-y-4">
          <Card>
            <CardHeader title="Profil" />
            <div className="grid gap-4 p-5 sm:grid-cols-2">
              <Field label="Nama cafe"><Input value={str("cafe_name")} onChange={(e) => set("cafe_name", e.target.value)} /></Field>
              <Field label="Telepon"><Input value={str("cafe_phone")} onChange={(e) => set("cafe_phone", e.target.value)} /></Field>
              <Field label="Alamat" className="sm:col-span-2"><Textarea value={str("cafe_address")} onChange={(e) => set("cafe_address", e.target.value)} /></Field>
              <Field label="Email"><Input value={str("cafe_email")} onChange={(e) => set("cafe_email", e.target.value)} /></Field>
              <Field label="Instagram"><Input value={str("social_instagram")} onChange={(e) => set("social_instagram", e.target.value)} placeholder="@kopisenja" /></Field>
              <Field label="TikTok"><Input value={str("social_tiktok")} onChange={(e) => set("social_tiktok", e.target.value)} /></Field>
              <Field label="Website"><Input value={str("social_website")} onChange={(e) => set("social_website", e.target.value)} /></Field>
            </div>
          </Card>
          <Card>
            <CardHeader title="Logo & QRIS statis" />
            <div className="grid gap-4 p-5 sm:grid-cols-2">
              {([["cafe_logo", "Logo cafe"], ["payment_qris_image", "Gambar QRIS statis"]] as const).map(([k, l]) => (
                <label key={k} className="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-border p-4 text-sm text-muted hover:border-primary">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  {s[`${k}_url`] ? <img src={String(s[`${k}_url`])} alt={l} className="h-28 w-28 rounded-xl object-contain" /> : <ImagePlus className="h-8 w-8" />}
                  <span>{l}</span>
                  <input type="file" accept="image/*" hidden onChange={(e) => e.target.files?.[0] && upload(k, e.target.files[0])} />
                </label>
              ))}
              <Field label="Info rekening transfer" className="sm:col-span-2"><Textarea value={str("payment_bank_info")} onChange={(e) => set("payment_bank_info", e.target.value)} /></Field>
            </div>
          </Card>
        </div>
      )}

      {tab === "transaksi" && (
        <Card>
          <div className="grid gap-4 p-5 sm:grid-cols-2">
            <Switch checked={bool("tax_enabled")} onChange={(v) => set("tax_enabled", v)} label="Kenakan pajak (PPN)" />
            <Field label="Pajak (%)"><Input type="number" step="0.01" value={String(s.tax_percent)} onChange={(e) => set("tax_percent", Number(e.target.value))} /></Field>
            <Switch checked={bool("service_enabled")} onChange={(v) => set("service_enabled", v)} label="Service charge (dine-in)" />
            <Field label="Service charge (%)"><Input type="number" step="0.01" value={String(s.service_percent)} onChange={(e) => set("service_percent", Number(e.target.value))} /></Field>
            <Field label="Pembulatan total">
              <Select value={String(s.rounding)} onChange={(e) => set("rounding", Number(e.target.value))}><option value="0">Tanpa pembulatan</option><option value="100">Rp 100</option><option value="500">Rp 500</option><option value="1000">Rp 1.000</option></Select>
            </Field>
            <Field label="Arah pembulatan">
              <Select value={str("rounding_mode")} onChange={(e) => set("rounding_mode", e.target.value)}><option value="nearest">Terdekat</option><option value="up">Ke atas</option><option value="down">Ke bawah</option></Select>
            </Field>
            <Field label="Prefix invoice" hint="Format: PREFIX-YYYYMMDD-0001"><Input value={str("invoice_prefix")} maxLength={6} onChange={(e) => set("invoice_prefix", e.target.value.toUpperCase())} /></Field>
            <Field label="Ukuran kertas struk">
              <Select value={str("receipt_paper")} onChange={(e) => set("receipt_paper", e.target.value)}><option value="58">58 mm</option><option value="80">80 mm</option></Select>
            </Field>
            <Field label="Footer struk" className="sm:col-span-2"><Textarea value={str("receipt_footer")} onChange={(e) => set("receipt_footer", e.target.value)} /></Field>
            <Switch checked={bool("loyalty_enabled")} onChange={(v) => set("loyalty_enabled", v)} label="Program poin loyalitas" />
            <Field label="Belanja per 1 poin (Rp)"><Input type="number" value={String(s.loyalty_spend_per_point)} onChange={(e) => set("loyalty_spend_per_point", Number(e.target.value))} /></Field>
            <Field label="Nilai 1 poin (Rp)"><Input type="number" value={String(s.loyalty_point_value)} onChange={(e) => set("loyalty_point_value", Number(e.target.value))} /></Field>
            <Field label="Minimal tukar poin"><Input type="number" value={String(s.loyalty_min_redeem)} onChange={(e) => set("loyalty_min_redeem", Number(e.target.value))} /></Field>
          </div>
        </Card>
      )}

      {tab === "operasional" && (
        <div className="space-y-4">
          <Card>
            <CardHeader title="Status toko" />
            <div className="space-y-4 p-5">
              <Switch checked={bool("store_closed")} onChange={(v) => set("store_closed", v)} label={<b className={bool("store_closed") ? "text-danger" : ""}>Mode toko tutup (pelanggan tidak bisa memesan)</b>} />
              <Field label="Pesan saat tutup"><Input value={str("store_closed_message")} onChange={(e) => set("store_closed_message", e.target.value)} /></Field>
              <Switch checked={bool("enforce_hours")} onChange={(v) => set("enforce_hours", v)} label="Tolak pesanan di luar jam operasional" />
              <div className="grid gap-2">
                {[1, 2, 3, 4, 5, 6, 7].map((d) => {
                  const h = s.opening_hours[String(d)] ?? { open: "08:00", close: "22:00", closed: false };
                  const upd = (p: Partial<typeof h>) => set("opening_hours", { ...s.opening_hours, [String(d)]: { ...h, ...p } });
                  return (
                    <div key={d} className="flex flex-wrap items-center gap-3">
                      <span className="w-20 text-sm font-medium">{DAYS[d]}</span>
                      <Input type="time" className="w-32" value={h.open} disabled={h.closed} onChange={(e) => upd({ open: e.target.value })} aria-label={`Buka ${DAYS[d]}`} />
                      <span>–</span>
                      <Input type="time" className="w-32" value={h.close} disabled={h.closed} onChange={(e) => upd({ close: e.target.value })} aria-label={`Tutup ${DAYS[d]}`} />
                      <Switch checked={h.closed} onChange={(v) => upd({ closed: v })} label="Libur" />
                    </div>
                  );
                })}
              </div>
            </div>
          </Card>
          <Card>
            <CardHeader title="Pesan online" description={<>Link untuk dibagikan ke IG/WA: <code className="rounded bg-surface-2 px-1.5">{APP_URL}/pesan/?mode=takeaway</code></>} />
            <div className="grid gap-4 p-5 sm:grid-cols-2">
              <Switch checked={bool("takeaway_enabled")} onChange={(v) => set("takeaway_enabled", v)} label="Takeaway (ambil sendiri)" />
              <Switch checked={bool("delivery_enabled")} onChange={(v) => set("delivery_enabled", v)} label="Pesan antar (delivery)" />
              <Field label="Ongkir flat (Rp)" hint="Kasir tetap bisa mengubah ongkir per pesanan."><Input type="number" min={0} value={String(s.delivery_fee ?? 0)} onChange={(e) => set("delivery_fee", Number(e.target.value))} /></Field>
              <Field label="Minimal pesanan delivery (Rp)"><Input type="number" min={0} value={String(s.delivery_min_order ?? 0)} onChange={(e) => set("delivery_min_order", Number(e.target.value))} /></Field>
              <Field label="Keterangan area antar" className="sm:col-span-2"><Input value={str("delivery_area_note")} onChange={(e) => set("delivery_area_note", e.target.value)} /></Field>
              <Switch checked={bool("preorder_enabled")} onChange={(v) => set("preorder_enabled", v)} label="Pre-order terjadwal (juga saat toko tutup)" />
              <Switch checked={bool("online_pay_first")} onChange={(v) => set("online_pay_first", v)} label="Wajib bayar dulu sebelum diproses (butuh payment gateway)" />
              <Field label="Jeda minimal pre-order (menit)"><Input type="number" min={0} value={String(s.preorder_lead_minutes ?? 15)} onChange={(e) => set("preorder_lead_minutes", Number(e.target.value))} /></Field>
              <Field label="Pre-order maks (hari ke depan)"><Input type="number" min={0} max={30} value={String(s.preorder_max_days ?? 3)} onChange={(e) => set("preorder_max_days", Number(e.target.value))} /></Field>
            </div>
          </Card>
          <Card>
            <CardHeader title="Pesanan & stok" />
            <div className="grid gap-4 p-5 sm:grid-cols-2">
              <Switch checked={bool("require_confirmation")} onChange={(v) => set("require_confirmation", v)} label="Pesanan pelanggan perlu konfirmasi staf (anti-prank)" />
              <Switch checked={bool("allow_online_payment")} onChange={(v) => set("allow_online_payment", v)} label="Izinkan bayar online (jika gateway aktif)" />
              <Switch checked={bool("auto_soldout")} onChange={(v) => set("auto_soldout", v)} label="Menu otomatis habis bila bahan utama habis" />
              <Field label="Potong stok saat">
                <Select value={str("stock_deduct_on")} onChange={(e) => set("stock_deduct_on", e.target.value)}><option value="paid">Pesanan dibayar</option><option value="served">Item disajikan</option></Select>
              </Field>
              <Field label="Maks qty per item"><Input type="number" value={String(s.max_qty_per_item)} onChange={(e) => set("max_qty_per_item", Number(e.target.value))} /></Field>
              <Field label="KDS: kuning setelah (menit)"><Input type="number" value={String(s.kds_warn_minutes)} onChange={(e) => set("kds_warn_minutes", Number(e.target.value))} /></Field>
              <Field label="KDS: merah setelah (menit)"><Input type="number" value={String(s.kds_danger_minutes)} onChange={(e) => set("kds_danger_minutes", Number(e.target.value))} /></Field>
            </div>
          </Card>
        </div>
      )}

      {tab === "sistem" && (
        <div className="space-y-4">
          <Card>
            <CardHeader title="Payment gateway" />
            <div className="space-y-2 p-5 text-sm">
              <p>Status: <b>{q.data?.meta.gateway ? `Aktif (${q.data.meta.gateway})` : "Nonaktif"}</b></p>
              {q.data?.meta.webhook_url && <p>URL webhook: <code className="rounded bg-surface-2 px-2 py-1">{q.data.meta.webhook_url}</code></p>}
              <p className="text-muted">Aktifkan dengan mengisi PAYMENT_GATEWAY dan kunci API di file .env backend. Kunci rahasia tidak pernah disimpan di frontend.</p>
              <p className="text-muted">Zona waktu: {q.data?.meta.timezone} · PHP {q.data?.meta.php_version}</p>
            </div>
          </Card>
          <Card>
            <CardHeader title={<span className="flex items-center gap-2"><Database className="h-4 w-4" /> Petunjuk backup database</span>} />
            <ol className="list-decimal space-y-2 p-5 pl-10 text-sm">
              <li>Login cPanel → <b>Backup</b> (atau <b>Backup Wizard</b>) → bagian <i>Download a MySQL Database Backup</i> → klik nama database. Simpan file .sql.gz di tempat aman (Google Drive/HDD).</li>
              <li>Alternatif: cPanel → <b>phpMyAdmin</b> → pilih database → tab <b>Export</b> → metode <i>Quick</i>, format <i>SQL</i> → Go.</li>
              <li>Backup juga folder <code>public/uploads</code> di backend (foto menu, logo) lewat <b>File Manager</b> → Compress → Download.</li>
              <li>Lakukan minimal <b>harian</b> saat jam tutup, simpan 7–30 cadangan terakhir. Uji restore sesekali di database terpisah.</li>
              <li>Restore: phpMyAdmin → pilih database (kosong) → tab <b>Import</b> → unggah file .sql.</li>
            </ol>
          </Card>
        </div>
      )}
    </div>
  );
}

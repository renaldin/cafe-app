"use client";

import { Bike, CalendarClock, ShoppingBag, Zap } from "lucide-react";
import { useMemo } from "react";
import { Field, Select, Textarea } from "@/components/ui/input";
import { cn } from "@/lib/cn";
import { fdate, rp } from "@/lib/format";
import type { StoreInfo } from "@/types";

export type OnlineMode = "takeaway" | "delivery";

/** Slot pre-order per 30 menit sesuai jam buka (validasi final tetap di server) */
export function buildSlots(st: StoreInfo, now = new Date()): { day: string; label: string; times: { value: string; label: string }[] }[] {
  const earliest = new Date(now.getTime() + (st.preorder_lead_minutes ?? 15) * 60000);
  const out: { day: string; label: string; times: { value: string; label: string }[] }[] = [];
  for (let d = 0; d <= (st.preorder_max_days ?? 3); d++) {
    const day = new Date(now);
    day.setDate(now.getDate() + d);
    const iso = (day.getDay() + 6) % 7 + 1; // 1=Senin..7=Minggu
    const h = st.opening_hours?.[String(iso)];
    let open = "07:00";
    let close = "22:00";
    if (st.enforce_hours) {
      if (!h || h.closed) continue;
      open = h.open;
      close = h.close;
    }
    const [oh, om] = open.split(":").map(Number);
    const [ch, cm] = close.split(":").map(Number);
    const start = new Date(day);
    start.setHours(oh, om, 0, 0);
    const end = new Date(day);
    end.setHours(ch, cm, 0, 0);
    if (end <= start) end.setDate(end.getDate() + 1);
    const times: { value: string; label: string }[] = [];
    // slot dimulai 30 menit setelah buka agar dapur siap
    for (let t = new Date(start.getTime() + 30 * 60000); t < end; t = new Date(t.getTime() + 30 * 60000)) {
      if (t < earliest) continue;
      times.push({ value: t.toISOString(), label: t.toTimeString().slice(0, 5) });
    }
    if (times.length) out.push({ day: day.toDateString(), label: d === 0 ? "Hari ini" : d === 1 ? "Besok" : fdate(day, "EEEE, d MMM"), times });
  }
  return out;
}

export function OnlineOptions({
  st,
  mode,
  setMode,
  address,
  setAddress,
  scheduled,
  setScheduled,
  subtotal,
}: {
  st: StoreInfo;
  mode: OnlineMode;
  setMode: (m: OnlineMode) => void;
  address: string;
  setAddress: (v: string) => void;
  scheduled: string;
  setScheduled: (v: string) => void;
  subtotal: number;
}) {
  const slots = useMemo(() => (st.preorder_enabled ? buildSlots(st) : []), [st]);
  const selDay = slots.find((s) => s.times.some((t) => t.value === scheduled))?.day ?? slots[0]?.day ?? "";
  const dayTimes = slots.find((s) => s.day === selDay)?.times ?? [];
  const canNow = st.is_open;
  const short = mode === "delivery" && st.delivery_min_order > subtotal;

  return (
    <div className="space-y-3">
      {st.delivery_enabled && (
        <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Cara pengambilan">
          {([["takeaway", "Ambil sendiri", ShoppingBag], ["delivery", "Diantar", Bike]] as const).map(([v, l, Icon]) => (
            <button key={v} role="radio" aria-checked={mode === v} onClick={() => setMode(v)} className={cn("flex items-center justify-center gap-2 rounded-xl border py-2.5 text-sm font-semibold", mode === v ? "border-primary bg-primary-soft text-primary" : "border-border text-muted")}>
              <Icon className="h-4 w-4" /> {l}
            </button>
          ))}
        </div>
      )}

      {mode === "delivery" && (
        <div className="space-y-1.5">
          <Field label="Alamat pengantaran *" htmlFor="addr">
            <Textarea id="addr" autoComplete="street-address" maxLength={500} value={address} onChange={(e) => setAddress(e.target.value)} placeholder="Nama jalan, nomor rumah, RT/RW, patokan" className="min-h-[64px]" />
          </Field>
          <p className="text-xs text-muted">
            Ongkir {rp(st.delivery_fee)}{st.delivery_min_order ? ` · minimal pesan ${rp(st.delivery_min_order)}` : ""}{st.delivery_area_note ? ` · ${st.delivery_area_note}` : ""}
          </p>
          {short && <p className="text-xs font-semibold text-danger">Tambah {rp(st.delivery_min_order - subtotal)} lagi untuk pesan antar.</p>}
        </div>
      )}

      {(st.preorder_enabled || !canNow) && (
        <div>
          <p className="mb-1.5 text-sm font-medium">Waktu {mode === "delivery" ? "pengantaran" : "pengambilan"}</p>
          <div className="grid grid-cols-2 gap-2">
            <button disabled={!canNow} onClick={() => setScheduled("")} className={cn("flex items-center justify-center gap-2 rounded-xl border py-2.5 text-sm font-semibold disabled:opacity-40", !scheduled && canNow ? "border-primary bg-primary-soft text-primary" : "border-border text-muted")}>
              <Zap className="h-4 w-4" /> Sekarang
            </button>
            <button disabled={!slots.length} onClick={() => !scheduled && dayTimes[0] && setScheduled(dayTimes[0].value)} className={cn("flex items-center justify-center gap-2 rounded-xl border py-2.5 text-sm font-semibold disabled:opacity-40", scheduled ? "border-primary bg-primary-soft text-primary" : "border-border text-muted")}>
              <CalendarClock className="h-4 w-4" /> Jadwalkan
            </button>
          </div>
          {!canNow && !scheduled && <p className="mt-1.5 text-xs text-warning">Toko sedang tutup. {slots.length ? "Pilih jadwal pre-order." : "Pre-order belum tersedia."}</p>}
          {scheduled && (
            <div className="mt-2 grid grid-cols-2 gap-2">
              <Select aria-label="Tanggal" value={selDay} onChange={(e) => { const s = slots.find((x) => x.day === e.target.value); if (s?.times[0]) setScheduled(s.times[0].value); }}>
                {slots.map((s) => <option key={s.day} value={s.day}>{s.label}</option>)}
              </Select>
              <Select aria-label="Jam" value={scheduled} onChange={(e) => setScheduled(e.target.value)}>
                {dayTimes.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </Select>
            </div>
          )}
        </div>
      )}

      {st.online_pay_first && <p className="rounded-xl bg-info-soft px-3 py-2 text-xs text-info">Pesanan online diproses setelah pembayaran online berhasil.</p>}
    </div>
  );
}

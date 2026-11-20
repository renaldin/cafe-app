"use client";

import { useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";
import { useSearchParams } from "next/navigation";
import QRCode from "qrcode";
import { Suspense, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/input";
import { Spinner } from "@/components/ui/card";
import { APP_URL, api } from "@/lib/api";
import type { TableRow } from "@/types";

function QrCard({ table, cafe }: { table: TableRow; cafe: string }) {
  const [src, setSrc] = useState("");
  useEffect(() => {
    void QRCode.toDataURL(`${APP_URL}/pesan/?t=${table.qr_token}`, { width: 600, margin: 1, errorCorrectionLevel: "M" }).then(setSrc);
  }, [table.qr_token]);
  return (
    <div className="flex break-inside-avoid flex-col items-center rounded-3xl border-2 border-dashed border-neutral-300 bg-white p-5 text-center text-black">
      <p className="text-sm font-semibold uppercase tracking-widest text-neutral-500">{cafe}</p>
      <p className="mt-1 text-4xl font-black">{table.code}</p>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      {src ? <img src={src} alt={`QR ${table.code}`} className="my-3 h-44 w-44" /> : <div className="my-3 h-44 w-44" />}
      <p className="font-bold">Scan untuk pesan</p>
      <p className="text-xs text-neutral-500">Pilih menu, kirim pesanan, dan pantau statusnya dari HP Anda.</p>
    </div>
  );
}

function QrSheet() {
  const ids = useSearchParams().get("ids")?.split(",").map(Number);
  const q = useQuery({ queryKey: ["tables-print"], queryFn: async () => (await api.get<{ data: TableRow[] }>("/tables")).data.data });
  const store = useQuery({ queryKey: ["pub-store-print"], queryFn: async () => (await api.get<{ data: { cafe_name: string } }>("/public/store")).data.data });
  const all = (Array.isArray(q.data) ? q.data : []).filter((t) => t.is_active);
  const [sel, setSel] = useState<number[] | null>(ids ?? null);
  const chosen = sel ?? all.map((t) => t.id);
  if (q.isLoading) return <div className="flex min-h-screen items-center justify-center"><Spinner /></div>;
  return (
    <div className="min-h-screen bg-neutral-100 p-6 print:bg-white print:p-4">
      <style>{`@media print { @page { size: A4; margin: 10mm } }`}</style>
      <div className="no-print mx-auto mb-6 max-w-4xl rounded-2xl bg-white p-4 text-black shadow">
        <div className="mb-3 flex items-center justify-between">
          <h1 className="text-lg font-bold">Cetak QR Meja</h1>
          <Button onClick={() => window.print()}><Printer className="h-4 w-4" /> Cetak ({chosen.length})</Button>
        </div>
        <div className="flex flex-wrap gap-3">
          <Checkbox checked={chosen.length === all.length} onChange={(v) => setSel(v ? all.map((t) => t.id) : [])} label="Semua" />
          {all.map((t) => <Checkbox key={t.id} checked={chosen.includes(t.id)} onChange={(v) => setSel(v ? [...chosen, t.id] : chosen.filter((x) => x !== t.id))} label={t.code} />)}
        </div>
        <p className="mt-2 text-xs text-neutral-500">Tips: cetak di kertas tebal/stiker, laminasi, lalu tempel di meja. URL: {APP_URL}/pesan/?t=…</p>
      </div>
      <div className="mx-auto grid max-w-4xl grid-cols-2 gap-6 md:grid-cols-3 print:grid-cols-3 print:gap-4">
        {all.filter((t) => chosen.includes(t.id)).map((t) => <QrCard key={t.id} table={t} cafe={store.data?.cafe_name ?? ""} />)}
      </div>
    </div>
  );
}

export default function QrCetakPage() {
  return (
    <Suspense fallback={null}>
      <QrSheet />
    </Suspense>
  );
}

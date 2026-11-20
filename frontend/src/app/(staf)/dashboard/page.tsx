"use client";

import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, ChefHat, ClipboardList, LayoutGrid, Receipt, TrendingUp, Wallet } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { Donut, SimpleBar } from "@/components/charts/charts";
import { Card, CardHeader, EmptyState, Skeleton, StatCard } from "@/components/ui/card";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { api } from "@/lib/api";
import { fdate, num, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import { useAuth } from "@/stores/auth";

type Summary = {
  orders_today: number; active_bills: number; active_items: number; pending_confirmation: number;
  tables: { total: number; empty: number; occupied: number; needs_cleaning: number };
  low_stock: { id: number; name: string; stock: number; min_stock: number; unit: string }[];
  revenue_today?: number; revenue_yesterday?: number; avg_order?: number;
};
type Charts = { daily: { date: string; total: number }[]; hourly: { hour: string; bills: number; total: number }[]; top_menus: { name: string; qty: number; total: number }[]; categories: { name: string; total: number }[]; payment_methods: { name: string; total: number }[] };

export default function DashboardPage() {
  const can = useCan();
  const user = useAuth((s) => s.user);
  const [range, setRange] = useState<"7d" | "30d">("7d");
  const finance = can("dashboard.finance");
  const s = useQuery({ queryKey: ["dash-summary"], queryFn: async () => (await api.get<{ data: Summary }>("/dashboard/summary")).data.data, refetchInterval: 30_000, refetchIntervalInBackground: false });
  const c = useQuery({ queryKey: ["dash-charts", range], enabled: finance, queryFn: async () => (await api.get<{ data: Charts }>("/dashboard/charts", { params: { range } })).data.data, refetchInterval: 120_000, refetchIntervalInBackground: false });
  const d = s.data;
  const delta = d?.revenue_yesterday ? Math.round((((d.revenue_today ?? 0) - d.revenue_yesterday) / d.revenue_yesterday) * 100) : null;

  return (
    <div>
      <PageHeader title={`Halo, ${user?.name?.split(" ")[0] ?? ""} 👋`} description={`Ringkasan hari ini · ${fdate(new Date(), "EEEE, d MMMM yyyy")}`} />
      {!d ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-28" />)}</div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {finance && <StatCard label="Omzet hari ini" value={rp(d.revenue_today ?? 0)} icon={<TrendingUp className="h-5 w-5" />} hint={delta !== null ? `${delta >= 0 ? "▲" : "▼"} ${Math.abs(delta)}% dibanding kemarin (s/d jam ini)` : "Belum ada pembanding"} />}
          <StatCard label="Transaksi selesai" value={num(d.orders_today)} icon={<Receipt className="h-5 w-5" />} tone="success" hint={finance ? `Rata-rata ${rp(d.avg_order ?? 0)}` : undefined} />
          <StatCard label="Meja terisi" value={`${d.tables.occupied} / ${d.tables.total}`} icon={<LayoutGrid className="h-5 w-5" />} tone="info" hint={`${d.tables.empty} kosong · ${d.tables.needs_cleaning} perlu dibersihkan`} />
          <StatCard label="Pesanan aktif" value={num(d.active_bills)} icon={<ChefHat className="h-5 w-5" />} tone="warning" hint={`${d.active_items} item di dapur${d.pending_confirmation ? ` · ${d.pending_confirmation} menunggu konfirmasi` : ""}`} />
        </div>
      )}

      {d && d.low_stock.length > 0 && (
        <Card className="mt-4 border-danger/40">
          <CardHeader title={<span className="flex items-center gap-2 text-danger"><AlertTriangle className="h-4 w-4" /> Stok menipis ({d.low_stock.length})</span>} action={<Link href="/bahan/" className="text-sm font-medium text-primary">Lihat bahan</Link>} />
          <div className="flex flex-wrap gap-2 p-4">
            {d.low_stock.map((i) => <span key={i.id} className="rounded-xl bg-danger-soft px-3 py-1.5 text-sm text-danger"><b>{i.name}</b> · {num(i.stock, 2)} / min {num(i.min_stock, 2)} {i.unit}</span>)}
          </div>
        </Card>
      )}

      {finance && (
        <>
          <div className="mb-3 mt-6 flex items-center justify-between">
            <h2 className="text-lg font-bold">Penjualan</h2>
            <Tabs value={range} onChange={setRange} items={[{ value: "7d", label: "7 hari" }, { value: "30d", label: "30 hari" }]} />
          </div>
          {!c.data ? (
            <div className="grid gap-4 lg:grid-cols-2"><Skeleton className="h-72" /><Skeleton className="h-72" /></div>
          ) : (
            <div className="grid gap-4 lg:grid-cols-2">
              <Card>
                <CardHeader title="Omzet harian" description={`Total ${rp(c.data.daily.reduce((s2, r) => s2 + r.total, 0))}`} />
                <div className="p-4"><SimpleBar data={c.data.daily} x="date" y="total" money formatX={(v) => fdate(v, "d/M")} /></div>
              </Card>
              <Card>
                <CardHeader title="Transaksi per jam (jam sibuk)" description="Jumlah bill berdasarkan jam pesanan dibuka" />
                <div className="p-4"><SimpleBar data={c.data.hourly} x="hour" y="bills" unit="transaksi" formatX={(v) => `${v}:00`} /></div>
              </Card>
              <Card>
                <CardHeader title="Menu terlaris" description="Berdasarkan jumlah terjual" />
                <div className="p-4">{c.data.top_menus.length ? <SimpleBar data={c.data.top_menus} x="name" y="qty" unit="porsi" horizontal height={Math.max(160, c.data.top_menus.length * 34)} /> : <EmptyState icon={<ClipboardList className="h-6 w-6" />} title="Belum ada penjualan" />}</div>
              </Card>
              <div className="grid gap-4">
                <Card>
                  <CardHeader title="Penjualan per kategori" />
                  <div className="p-4">{c.data.categories.length ? <Donut data={c.data.categories} /> : <p className="py-6 text-center text-sm text-muted">Belum ada data</p>}</div>
                </Card>
                <Card>
                  <CardHeader title={<span className="flex items-center gap-2"><Wallet className="h-4 w-4" /> Metode pembayaran</span>} />
                  <div className="p-4">{c.data.payment_methods.length ? <Donut data={c.data.payment_methods} /> : <p className="py-6 text-center text-sm text-muted">Belum ada data</p>}</div>
                </Card>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

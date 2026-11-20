"use client";

import { useMutation } from "@tanstack/react-query";
import { Ban, BellRing, Check, ChefHat, Clock, ListPlus, Wallet, X } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { ShiftBanner } from "@/components/staff/shift-gate";
import { SoldOutModal } from "@/components/staff/soldout-modal";
import { SoundToggle } from "@/components/staff/sound-toggle";
import { Button } from "@/components/ui/button";
import { Badge, Card, CardHeader, EmptyState, Skeleton } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { Tabs } from "@/components/ui/tabs";
import { useNewOrderAlert, useTick } from "@/hooks/useNewOrderAlert";
import { usePolling } from "@/hooks/usePolling";
import { api, errMsg } from "@/lib/api";
import { cn } from "@/lib/cn";
import { ITEM_STATUS, ORDER_TYPE, ago, fdatetime, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import { playCall } from "@/lib/sound";
import type { Order } from "@/types";

type FeedBill = {
  id: number; invoice_no: string; label: string; type: string; status: string; table: { id: number; code: string } | null;
  customer_name: string | null; total: number; paid_amount: number; item_count: number; progress: string;
  pending_confirmation: boolean; bill_requested: boolean; opened_at: string; scheduled_at: string | null; awaiting_online_payment: boolean;
};
type Feed = { bills: FeedBill[]; pending_orders: Order[]; calls: { id: number; type: string; table: { code: string } | null; created_at: string }[]; latest_order_id: number };

export default function KasirPage() {
  const router = useRouter();
  const can = useCan();
  const [tab, setTab] = useState<"all" | "dine_in" | "takeaway" | "awaiting">("all");
  const [search, setSearch] = useState("");
  const [soldOut, setSoldOut] = useState(false);
  useTick(30000);
  const q = usePolling<Feed>(api, ["feed"], "/orders/feed", { interval: 5000 });
  useNewOrderAlert(q.data?.latest_order_id);

  const confirmOrder = useMutation({
    mutationFn: async (id: number) => api.post(`/orders/${id}/confirm`),
    onSuccess: (r) => { toast.success(r.data.message); void q.refetch(); },
    onError: (e) => toast.error(errMsg(e)),
  });
  const rejectOrder = useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string }) => api.post(`/orders/${id}/reject`, { reason }),
    onSuccess: (r) => { toast.success(r.data.message); void q.refetch(); },
    onError: (e) => toast.error(errMsg(e)),
  });
  const handleCall = useMutation({
    mutationFn: async (id: number) => api.patch(`/table-calls/${id}`),
    onSuccess: () => void q.refetch(),
  });

  const bills = useMemo(() => {
    const s = search.toLowerCase();
    return (q.data?.bills ?? []).filter((b) => {
      if (tab === "dine_in" && b.type !== "dine_in") return false;
      if (tab === "takeaway" && b.type === "dine_in") return false;
      if (tab === "awaiting" && b.status !== "awaiting_payment") return false;
      return !s || b.label.toLowerCase().includes(s) || b.invoice_no.toLowerCase().includes(s) || (b.customer_name ?? "").toLowerCase().includes(s);
    });
  }, [q.data, tab, search]);

  const calls = q.data?.calls ?? [];
  // pesanan "bayar dulu" tidak perlu dikonfirmasi manual (otomatis masuk dapur setelah lunas)
  const pending = (q.data?.pending_orders ?? []).filter((o) => !o.requires_payment);

  return (
    <div>
      <PageHeader
        title="Kasir"
        description="Pesanan aktif diperbarui otomatis setiap 5 detik."
        actions={
          <>
            <SoundToggle />
            {can("menu.toggle") && <Button variant="outline" size="sm" onClick={() => setSoldOut(true)}><Ban className="h-4 w-4" /> Menu habis</Button>}
            {can("order.create") && <Link href="/pesanan/baru/"><Button size="sm"><ListPlus className="h-4 w-4" /> Pesanan manual</Button></Link>}
          </>
        }
      />
      <ShiftBanner />

      {calls.length > 0 && (
        <div className="mb-4 flex flex-wrap gap-2">
          {calls.map((c) => (
            <button key={c.id} onClick={() => { playCall(); handleCall.mutate(c.id); }} className="animate-pulse-ring flex items-center gap-2 rounded-xl bg-accent px-3 py-2 text-sm font-semibold text-white">
              <BellRing className="h-4 w-4" /> Meja {c.table?.code}: {c.type === "bill" ? "minta bill" : "panggil pelayan"} · {ago(c.created_at)}
              <Check className="h-4 w-4" />
            </button>
          ))}
        </div>
      )}

      {pending.length > 0 && (
        <Card className="mb-4 border-warning/50">
          <CardHeader title={<span className="flex items-center gap-2 text-warning"><Clock className="h-4 w-4" /> Menunggu konfirmasi ({pending.length})</span>} description="Pesanan dari QR/link pelanggan. Konfirmasi agar diteruskan ke dapur." />
          <div className="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
            {pending.map((o) => (
              <div key={o.id} className="rounded-xl border border-border p-3">
                <div className="mb-1 flex justify-between">
                  <b>{o.bill?.table ? `Meja ${o.bill.table.code}` : `Antrean #${o.bill?.queue_no}`}</b>
                  <span className="text-xs text-muted">{ago(o.created_at)}</span>
                </div>
                <ul className="mb-3 text-sm">
                  {o.items?.map((i) => <li key={i.id}>{i.qty}× {i.name} {i.modifiers?.length ? <span className="text-muted">({i.modifiers.map((m) => m.name).join(", ")})</span> : null}</li>)}
                </ul>
                {can("order.confirm") && (
                  <div className="flex gap-2">
                    <Button size="sm" variant="success" className="flex-1" onClick={() => confirmOrder.mutate(o.id)} loading={confirmOrder.isPending && confirmOrder.variables === o.id}><Check className="h-4 w-4" /> Konfirmasi</Button>
                    <Button size="sm" variant="outline" onClick={async () => { const r = await confirm({ title: "Tolak pesanan?", danger: true, reason: "Alasan penolakan", confirmText: "Tolak" }); if (r.ok) rejectOrder.mutate({ id: o.id, reason: r.reason! }); }}><X className="h-4 w-4" /> Tolak</Button>
                  </div>
                )}
              </div>
            ))}
          </div>
        </Card>
      )}

      <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <Tabs
          value={tab}
          onChange={setTab}
          items={[
            { value: "all", label: "Semua", count: q.data?.bills.length },
            { value: "dine_in", label: "Dine-in" },
            { value: "takeaway", label: "Takeaway/Delivery" },
            { value: "awaiting", label: "Minta bill", count: q.data?.bills.filter((b) => b.status === "awaiting_payment").length },
          ]}
        />
        <SearchInput value={search} onChange={setSearch} placeholder="Cari meja, invoice, nama..." className="sm:w-72" />
      </div>

      {q.isLoading ? (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{Array.from({ length: 8 }).map((_, i) => <Skeleton key={i} className="h-36" />)}</div>
      ) : bills.length === 0 ? (
        <Card><EmptyState icon={<Wallet className="h-6 w-6" />} title="Tidak ada bill aktif" description="Pesanan baru dari pelanggan atau pelayan akan tampil di sini." /></Card>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {bills.map((b) => (
            <button
              key={b.id}
              onClick={() => router.push(`/kasir/bill/?id=${b.id}`)}
              className={cn(
                "card-shadow rounded-2xl border bg-surface p-4 text-left transition hover:-translate-y-0.5 hover:border-primary/50",
                b.status === "awaiting_payment" ? "border-warning" : "border-border",
              )}
            >
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="text-lg font-bold">{b.label}</p>
                  <p className="text-xs text-muted">{b.invoice_no}</p>
                </div>
                <Badge className="bg-surface-2 text-muted">{ORDER_TYPE[b.type]}</Badge>
              </div>
              {b.customer_name && <p className="mt-1 text-sm">{b.customer_name}</p>}
              <div className="mt-3 flex flex-wrap gap-1">
                <Badge className={ITEM_STATUS[b.progress]?.className ?? "bg-surface-2"}><ChefHat className="h-3 w-3" /> {ITEM_STATUS[b.progress]?.text ?? b.progress}</Badge>
                {b.scheduled_at && <Badge className="bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Pre-order {fdatetime(b.scheduled_at)}</Badge>}
                {b.awaiting_online_payment ? <Badge className="bg-info-soft text-info">Menunggu bayar online</Badge> : b.pending_confirmation && <Badge className="bg-warning-soft text-warning">Perlu konfirmasi</Badge>}
                {b.status === "awaiting_payment" && <Badge className="bg-warning-soft text-warning">Minta bill</Badge>}
                {b.paid_amount > 0 && <Badge className="bg-info-soft text-info">Bayar sebagian</Badge>}
              </div>
              <div className="mt-3 flex items-end justify-between">
                <span className="text-xs text-muted">{b.item_count} item · {ago(b.opened_at)}</span>
                <span className="text-lg font-bold text-primary">{rp(b.total)}</span>
              </div>
            </button>
          ))}
        </div>
      )}
      <SoldOutModal open={soldOut} onClose={() => setSoldOut(false)} />
    </div>
  );
}

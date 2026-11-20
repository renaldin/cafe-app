"use client";

import { useMutation } from "@tanstack/react-query";
import { BellRing, Bike, CalendarClock, CheckCircle2, ChefHat, Clock, CreditCard, Plus, Receipt, Soup, Wallet } from "lucide-react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useRef } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Badge, Card, EmptyState, Skeleton } from "@/components/ui/card";
import { ThemeToggle } from "@/components/ui/theme-toggle";
import { usePolling } from "@/hooks/usePolling";
import { errMsg, publicApi } from "@/lib/api";
import { cn } from "@/lib/cn";
import { ITEM_STATUS, fdatetime, ftime, rp } from "@/lib/format";
import type { Bill } from "@/types";

type Track = { bill: Bill; progress: string; open_calls: string[]; online_payment: boolean };

const STEPS = [
  { key: "new", label: "Diterima", icon: Receipt },
  { key: "processing", label: "Diproses", icon: ChefHat },
  { key: "ready", label: "Siap", icon: Soup },
  { key: "served", label: "Disajikan", icon: CheckCircle2 },
];

function Progress({ status }: { status: string }) {
  const idx = Math.max(0, STEPS.findIndex((s) => s.key === status));
  return (
    <ol className="flex items-start justify-between" aria-label="Progres pesanan">
      {STEPS.map((s, i) => {
        const Icon = s.icon;
        const done = i <= idx;
        return (
          <li key={s.key} className="relative flex flex-1 flex-col items-center text-center">
            {i > 0 && <span className={cn("absolute right-1/2 top-5 h-1 w-full -translate-y-1/2", i <= idx ? "bg-primary" : "bg-surface-3")} aria-hidden />}
            <span className={cn("relative z-10 flex h-10 w-10 items-center justify-center rounded-full", done ? "bg-primary text-primary-fg" : "bg-surface-3 text-muted", i === idx && status !== "served" && "animate-pulse-ring")}>
              <Icon className="h-5 w-5" />
            </span>
            <span className={cn("mt-1.5 text-xs font-medium", done ? "text-fg" : "text-muted")} aria-current={i === idx ? "step" : undefined}>
              {s.label}
            </span>
          </li>
        );
      })}
    </ol>
  );
}

function TrackPage() {
  const params = useSearchParams();
  const key = params.get("k") ?? "";
  const token = params.get("t");
  const q = usePolling<Track>(publicApi, ["track", key], `/track/${key}`, { interval: 5000, enabled: /^[a-z0-9]{32}$/.test(key) });
  const prevProgress = useRef<string | null>(null);

  useEffect(() => {
    const p = q.data?.progress;
    if (p && prevProgress.current && p !== prevProgress.current && p === "ready") {
      toast.success("Pesanan Anda sudah siap! ðŸŽ‰");
      try { navigator.vibrate?.([150, 80, 150]); } catch { /* */ }
    }
    if (p) prevProgress.current = p;
  }, [q.data?.progress]);

  const call = useMutation({
    mutationFn: async (type: "waiter" | "bill") => (await publicApi.post(`/tables/${token}/call`, { type })).data,
    onSuccess: (r) => {
      toast.success(r.message);
      void q.refetch();
    },
    onError: (e) => toast.error(errMsg(e)),
  });
  const pay = useMutation({
    mutationFn: async () => (await publicApi.post(`/track/${key}/pay`, { return_url: window.location.href })).data.data as { url: string },
    onSuccess: (d) => {
      if (d.url) window.location.href = d.url;
    },
    onError: (e) => toast.error(errMsg(e)),
  });

  if (!key) return <EmptyState title="Kode pesanan tidak ada" description="Buka kembali dari halaman pemesanan." />;
  if (q.isError) return <div className="p-6"><EmptyState title="Pesanan tidak ditemukan" description="Kode pelacakan tidak valid." /></div>;
  const d = q.data;
  const bill = d?.bill;
  const paid = bill?.status === "paid";
  const orders = bill?.orders ?? [];

  return (
    <div className="mx-auto min-h-screen max-w-xl px-4 pb-24 pt-4">
      <header className="mb-4 flex items-center justify-between">
        <div>
          <p className="text-sm text-muted">Status pesanan</p>
          <h1 className="text-xl font-bold">{bill ? bill.label : <Skeleton className="h-6 w-28" />}</h1>
        </div>
        <ThemeToggle />
      </header>

      {!d && <div className="space-y-3"><Skeleton className="h-36" /><Skeleton className="h-48" /></div>}

      {bill && (
        <div className="space-y-4">
          {bill.type !== "dine_in" && bill.queue_no && (
            <Card className="p-5 text-center">
              <p className="text-sm text-muted">Nomor antrean Anda</p>
              <p className="text-5xl font-black tracking-tight text-primary">#{String(bill.queue_no).padStart(3, "0")}</p>
              {bill.customer_name && <p className="mt-1 text-sm">a.n. {bill.customer_name}</p>}
            </Card>
          )}

          {(bill.scheduled_at || bill.delivery_address) && (
            <Card className="space-y-1 p-4 text-sm">
              {bill.scheduled_at && <p className="flex items-center gap-2"><CalendarClock className="h-4 w-4 text-primary" /> Pre-order: <b>{fdatetime(bill.scheduled_at)}</b></p>}
              {bill.delivery_address && <p className="flex items-start gap-2"><Bike className="mt-0.5 h-4 w-4 shrink-0 text-primary" /> Diantar ke: {bill.delivery_address}</p>}
            </Card>
          )}

          <Card className="p-5">
            {paid ? (
              <div className="flex items-center gap-3 text-success">
                <CheckCircle2 className="h-8 w-8" />
                <div>
                  <p className="font-bold">Pembayaran lunas</p>
                  <p className="text-sm text-muted">Terima kasih telah berkunjung!</p>
                </div>
              </div>
            ) : orders.some((o) => o.status === "pending_confirmation" && o.requires_payment) ? (
              <div className="flex items-center gap-3 text-info">
                <CreditCard className="h-7 w-7" />
                <div>
                  <p className="font-bold">Menunggu pembayaran</p>
                  <p className="text-sm text-muted">Pesanan akan diproses setelah pembayaran online berhasil.</p>
                </div>
              </div>
            ) : orders.some((o) => o.status === "pending_confirmation") ? (
              <div className="flex items-center gap-3 text-warning">
                <Clock className="h-7 w-7" />
                <div>
                  <p className="font-bold">Menunggu konfirmasi pelayan</p>
                  <p className="text-sm text-muted">Pesanan akan diteruskan ke dapur setelah dikonfirmasi.</p>
                </div>
              </div>
            ) : (
              <Progress status={d.progress} />
            )}
            <p className="mt-4 text-center text-[11px] text-muted">Halaman ini diperbarui otomatis setiap beberapa detik.</p>
          </Card>

          {orders.map((o) => (
            <Card key={o.id} className="p-4">
              <div className="mb-2 flex items-center justify-between">
                <p className="font-semibold">Pesanan #{o.order_no}</p>
                <span className="text-xs text-muted">{ftime(o.created_at)}</span>
              </div>
              {o.status === "rejected" && <p className="mb-2 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">Pesanan ditolak{o.note ? `: ${o.note}` : ""}</p>}
              <ul className="space-y-2">
                {o.items?.map((it) => (
                  <li key={it.id} className="flex items-start justify-between gap-2 text-sm">
                    <div className="min-w-0">
                      <p className={cn("font-medium", it.status === "cancelled" && "line-through opacity-60")}>
                        {it.qty}Ã— {it.name}
                      </p>
                      {it.modifiers && it.modifiers.length > 0 && <p className="text-xs text-muted">{it.modifiers.map((m) => m.name).join(", ")}</p>}
                    </div>
                    <Badge className={ITEM_STATUS[it.status]?.className}>{ITEM_STATUS[it.status]?.text}</Badge>
                  </li>
                ))}
              </ul>
            </Card>
          ))}

          <Card className="p-4">
            <dl className="space-y-1 text-sm">
              <div className="flex justify-between"><dt className="text-muted">Subtotal</dt><dd>{rp(bill.subtotal)}</dd></div>
              {bill.promo_amount + bill.discount_amount + bill.voucher_amount + bill.points_amount > 0 && (
                <div className="flex justify-between text-success"><dt>Diskon</dt><dd>-{rp(bill.promo_amount + bill.discount_amount + bill.voucher_amount + bill.points_amount)}</dd></div>
              )}
              {bill.service_charge > 0 && <div className="flex justify-between"><dt className="text-muted">Service</dt><dd>{rp(bill.service_charge)}</dd></div>}
              {bill.tax > 0 && <div className="flex justify-between"><dt className="text-muted">Pajak</dt><dd>{rp(bill.tax)}</dd></div>}
              {bill.delivery_fee > 0 && <div className="flex justify-between"><dt className="text-muted">Ongkir</dt><dd>{rp(bill.delivery_fee)}</dd></div>}
              {bill.rounding !== 0 && <div className="flex justify-between"><dt className="text-muted">Pembulatan</dt><dd>{rp(bill.rounding)}</dd></div>}
              <div className="flex justify-between border-t border-border pt-1.5 text-base font-bold"><dt>Total</dt><dd>{rp(bill.total)}</dd></div>
              {bill.paid_amount > 0 && !paid && <div className="flex justify-between text-success"><dt>Sudah dibayar</dt><dd>{rp(bill.paid_amount)}</dd></div>}
            </dl>
            <p className="mt-2 text-xs text-muted">No. {bill.invoice_no}</p>
          </Card>

          {!paid && bill.status !== "void" && (
            <div className="grid gap-2">
              {d.online_payment && (
                <Button size="lg" onClick={() => pay.mutate()} loading={pay.isPending}>
                  <CreditCard className="h-5 w-5" /> Bayar online (QRIS / e-wallet)
                </Button>
              )}
              {token ? (
                <div className="grid grid-cols-2 gap-2">
                  <Button variant="outline" size="lg" onClick={() => call.mutate("waiter")} loading={call.isPending && call.variables === "waiter"} disabled={d.open_calls.includes("waiter")}>
                    <BellRing className="h-5 w-5" /> {d.open_calls.includes("waiter") ? "Dipanggil" : "Panggil pelayan"}
                  </Button>
                  <Button variant="outline" size="lg" onClick={() => call.mutate("bill")} loading={call.isPending && call.variables === "bill"} disabled={bill.bill_requested}>
                    <Wallet className="h-5 w-5" /> {bill.bill_requested ? "Bill diminta" : "Minta bill"}
                  </Button>
                </div>
              ) : (
                <p className="rounded-2xl bg-surface-2 p-3 text-center text-sm text-muted">Silakan bayar di kasir dengan menyebutkan nomor antrean Anda.</p>
              )}
            </div>
          )}
        </div>
      )}

      {token && !paid && (
        <div className="fixed inset-x-0 bottom-0 mx-auto max-w-xl p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
          <Link href={`/pesan/?t=${token}`} className="flex h-12 items-center justify-center gap-2 rounded-2xl bg-primary font-semibold text-primary-fg shadow-xl">
            <Plus className="h-5 w-5" /> Pesan lagi
          </Link>
        </div>
      )}
    </div>
  );
}

export default function LacakPage() {
  return (
    <Suspense fallback={<div className="mx-auto max-w-xl space-y-3 p-4"><Skeleton className="h-36" /><Skeleton className="h-48" /></div>}>
      <TrackPage />
    </Suspense>
  );
}

"use client";

import { useMutation } from "@tanstack/react-query";
import { AnimatePresence, motion } from "framer-motion";
import { Ban, ChefHat, Clock, Coffee, Maximize, Minimize, Printer, RefreshCw } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { SoldOutModal } from "@/components/staff/soldout-modal";
import { SoundToggle } from "@/components/staff/sound-toggle";
import { Button } from "@/components/ui/button";
import { Badge, EmptyState, Skeleton } from "@/components/ui/card";
import { Tabs } from "@/components/ui/tabs";
import { useNewOrderAlert, useTick } from "@/hooks/useNewOrderAlert";
import { usePolling } from "@/hooks/usePolling";
import { api, errMsg } from "@/lib/api";
import { cn } from "@/lib/cn";
import { ITEM_STATUS, ORDER_TYPE, fdatetime, ftime, minutesSince } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { OrderItem } from "@/types";

type CardData = { order_id: number; order_no: number; label: string; type: string; customer_name: string | null; scheduled_at: string | null; note: string | null; created_at: string; status: string; items: OrderItem[] };
type Kds = { cards: CardData[]; latest_order_id: number; warn_minutes: number; danger_minutes: number; stations: { id: number; name: string; code: string }[] };

const NEXT: Record<string, string | undefined> = { new: "processing", processing: "ready", ready: "served" };
const NEXT_LABEL: Record<string, string> = { new: "Proses", processing: "Siap", ready: "Sajikan" };

function readStation() {
  if (typeof window === "undefined") return "all";
  try {
    return localStorage.getItem("kds-station") ?? "all";
  } catch {
    return "all";
  }
}

export default function DapurPage() {
  const can = useCan();
  const [station, setStation] = useState<string>(readStation);
  const [filter, setFilter] = useState<"all" | "new" | "processing" | "ready">("all");
  const [fullscreen, setFullscreen] = useState(false);
  const [soldOut, setSoldOut] = useState(false);
  const [printCard, setPrintCard] = useState<CardData | null>(null);
  useTick(15000);

  const q = usePolling<Kds>(api, ["kds"], "/kds", { interval: 4000, params: { station } });
  useNewOrderAlert(q.data?.latest_order_id, "Pesanan baru di dapur/bar!");

  useEffect(() => {
    try { localStorage.setItem("kds-station", station); } catch { /* */ }
  }, [station]);
  useEffect(() => {
    const h = () => setFullscreen(!!document.fullscreenElement);
    document.addEventListener("fullscreenchange", h);
    return () => document.removeEventListener("fullscreenchange", h);
  }, []);
  useEffect(() => {
    if (!printCard) return;
    const t = setTimeout(() => {
      window.print();
      setPrintCard(null);
    }, 100);
    return () => clearTimeout(t);
  }, [printCard]);

  const itemMut = useMutation({
    mutationFn: async ({ id, status }: { id: number; status: string }) => api.patch(`/kds/items/${id}`, { status }),
    onSuccess: () => void q.refetch(),
    onError: (e) => toast.error(errMsg(e)),
  });
  const orderMut = useMutation({
    mutationFn: async ({ id, status }: { id: number; status: string }) => api.patch(`/kds/orders/${id}`, { status, station }),
    onSuccess: () => void q.refetch(),
    onError: (e) => toast.error(errMsg(e)),
  });

  const cards = (q.data?.cards ?? []).filter((c) => filter === "all" || c.items.some((i) => i.status === filter));
  const counts = { new: 0, processing: 0, ready: 0 } as Record<string, number>;
  (q.data?.cards ?? []).forEach((c) => c.items.forEach((i) => (counts[i.status] = (counts[i.status] ?? 0) + 1)));
  const warn = q.data?.warn_minutes ?? 5;
  const danger = q.data?.danger_minutes ?? 10;
  const canUpdate = can("kds.update");

  const toggleFs = () => (document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()).catch(() => undefined);

  return (
    <div className="min-h-[calc(100vh-5rem)]">
      <div className="no-print mb-4 flex flex-wrap items-center gap-2">
        <h1 className="mr-2 flex items-center gap-2 text-2xl font-bold">
          <ChefHat className="h-6 w-6 text-primary" /> Layar Dapur / Bar
        </h1>
        <Tabs
          value={station}
          onChange={setStation}
          items={[{ value: "all", label: "Semua" }, ...(q.data?.stations ?? []).map((s) => ({ value: s.code, label: s.code === "bar" ? <span className="flex items-center gap-1"><Coffee className="h-4 w-4" />{s.name}</span> : <span className="flex items-center gap-1"><ChefHat className="h-4 w-4" />{s.name}</span> }))]}
        />
        <Tabs
          value={filter}
          onChange={setFilter}
          items={[
            { value: "all", label: "Aktif" },
            { value: "new", label: "Baru", count: counts.new },
            { value: "processing", label: "Diproses", count: counts.processing },
            { value: "ready", label: "Siap", count: counts.ready },
          ]}
        />
        <div className="ml-auto flex gap-2">
          {can("menu.toggle") && (
            <Button variant="outline" size="sm" onClick={() => setSoldOut(true)}>
              <Ban className="h-4 w-4" /> <span className="hidden sm:inline">Menu habis</span>
            </Button>
          )}
          <SoundToggle />
          <Button variant="outline" size="sm" onClick={() => q.refetch()} aria-label="Muat ulang">
            <RefreshCw className={cn("h-4 w-4", q.isFetching && "animate-spin")} />
          </Button>
          <Button variant="outline" size="sm" onClick={toggleFs} aria-label="Layar penuh">
            {fullscreen ? <Minimize className="h-4 w-4" /> : <Maximize className="h-4 w-4" />}
          </Button>
        </div>
      </div>

      {q.isLoading && (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
          {Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-64" />)}
        </div>
      )}
      {!q.isLoading && cards.length === 0 && (
        <EmptyState icon={<ChefHat className="h-6 w-6" />} title="Belum ada pesanan" description="Pesanan baru akan muncul otomatis di sini dengan notifikasi suara." />
      )}

      <div className="no-print grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <AnimatePresence>
          {cards.map((c) => {
            const mins = minutesSince(c.created_at);
            const tone = mins >= danger ? "danger" : mins >= warn ? "warning" : "ok";
            const actionable = c.items.filter((i) => NEXT[i.status]);
            const minStatus = ["new", "processing", "ready"].find((s) => c.items.some((i) => i.status === s));
            return (
              <motion.article
                key={c.order_id}
                layout
                initial={{ opacity: 0, scale: 0.96 }}
                animate={{ opacity: 1, scale: 1 }}
                exit={{ opacity: 0, scale: 0.96 }}
                className={cn(
                  "flex flex-col overflow-hidden rounded-2xl border-2 bg-surface",
                  tone === "danger" ? "border-danger" : tone === "warning" ? "border-warning" : "border-border",
                )}
              >
                <header className={cn("flex items-center justify-between px-4 py-3", tone === "danger" ? "bg-danger text-white dark:text-black" : tone === "warning" ? "bg-warning text-white dark:text-black" : "bg-surface-2")}>
                  <div>
                    <p className="text-xl font-black leading-tight">{c.label}</p>
                    <p className="text-xs opacity-80">
                      #{c.order_no} · {ORDER_TYPE[c.type]} {c.customer_name ? `· ${c.customer_name}` : ""}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="flex items-center justify-end gap-1 text-2xl font-black tabular-nums">
                      <Clock className="h-5 w-5" />
                      {mins}′
                    </p>
                    <p className="text-xs opacity-80">{ftime(c.created_at)}</p>
                  </div>
                </header>
                {c.scheduled_at && <p className="border-b border-border bg-violet-100 px-4 py-2 text-sm font-bold text-violet-800 dark:bg-violet-500/15 dark:text-violet-200">⏰ Pre-order: siap {fdatetime(c.scheduled_at)}</p>}
                {c.note && <p className="border-b border-border bg-warning-soft px-4 py-2 text-sm font-semibold text-warning">📝 {c.note}</p>}
                <ul className="flex-1 divide-y divide-border">
                  {c.items.map((it) => (
                    <li key={it.id}>
                      <button
                        disabled={!canUpdate || !NEXT[it.status] || itemMut.isPending}
                        onClick={() => NEXT[it.status] && itemMut.mutate({ id: it.id, status: NEXT[it.status]! })}
                        className="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-surface-2 disabled:cursor-default"
                        title={NEXT[it.status] ? `Ketuk untuk: ${NEXT_LABEL[it.status]}` : undefined}
                      >
                        <span className="flex h-9 min-w-9 items-center justify-center rounded-xl bg-primary-soft text-lg font-black text-primary">{it.qty}</span>
                        <span className="min-w-0 flex-1">
                          <span className="block text-lg font-bold leading-tight">{it.name}</span>
                          {it.modifiers && it.modifiers.length > 0 && <span className="block text-sm text-muted">{it.modifiers.map((m) => m.name).join(" · ")}</span>}
                          {it.note && <span className="mt-0.5 block text-sm font-semibold text-danger">⚠ {it.note}</span>}
                        </span>
                        <Badge className={cn("mt-1", ITEM_STATUS[it.status]?.className)}>{ITEM_STATUS[it.status]?.text}</Badge>
                      </button>
                    </li>
                  ))}
                </ul>
                <footer className="flex gap-2 border-t border-border p-3">
                  {canUpdate && minStatus && NEXT[minStatus] && (
                    <Button
                      size="lg"
                      className="flex-1"
                      variant={minStatus === "processing" ? "success" : "primary"}
                      loading={orderMut.isPending && orderMut.variables?.id === c.order_id}
                      onClick={() => orderMut.mutate({ id: c.order_id, status: NEXT[minStatus]! })}
                    >
                      {minStatus === "new" ? "Proses semua" : minStatus === "processing" ? "Semua siap" : "Sudah disajikan"}
                    </Button>
                  )}
                  <Button size="lg" variant="outline" onClick={() => setPrintCard(c)} aria-label="Cetak tiket">
                    <Printer className="h-5 w-5" />
                  </Button>
                  {actionable.length === 0 && <span className="text-sm text-muted">Selesai</span>}
                </footer>
              </motion.article>
            );
          })}
        </AnimatePresence>
      </div>

      {/* Tiket dapur untuk dicetak */}
      {printCard && (
        <div className="print-only p-3 font-mono text-[13px] text-black" style={{ width: "58mm" }}>
          <p className="text-center text-lg font-bold">{printCard.label}</p>
          <p className="text-center">Pesanan #{printCard.order_no} · {ftime(printCard.created_at)}</p>
          <hr className="my-1 border-dashed border-black" />
          {printCard.items.map((i) => (
            <div key={i.id} className="mb-1">
              <p className="font-bold">{i.qty}x {i.name}</p>
              {i.modifiers?.map((m) => <p key={m.name}>  - {m.name}</p>)}
              {i.note && <p>  * {i.note}</p>}
            </div>
          ))}
          {printCard.note && <p className="mt-1">Catatan: {printCard.note}</p>}
        </div>
      )}
      <SoldOutModal open={soldOut} onClose={() => setSoldOut(false)} />
    </div>
  );
}

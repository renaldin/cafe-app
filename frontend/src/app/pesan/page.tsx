"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { AnimatePresence, motion } from "framer-motion";
import { AlertCircle, ChevronRight, ClipboardList, MapPin, ShoppingBag, Trash2, UtensilsCrossed } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { ItemSheet } from "@/components/customer/item-sheet";
import { OnlineOptions, type OnlineMode } from "@/components/customer/online-options";
import { MenuCard } from "@/components/customer/menu-card";
import { Button } from "@/components/ui/button";
import { EmptyState, Skeleton } from "@/components/ui/card";
import { Field, Input, Textarea } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { QtyStepper } from "@/components/ui/qty";
import { SearchInput } from "@/components/ui/search-input";
import { ThemeToggle } from "@/components/ui/theme-toggle";
import { useMounted } from "@/hooks/useMounted";
import { errMsg, publicApi } from "@/lib/api";
import { cn } from "@/lib/cn";
import { rp } from "@/lib/format";
import { linePrice, useCart } from "@/stores/cart";
import type { CatalogCategory, Menu, StoreInfo } from "@/types";

const EMPTY_LINES: never[] = [];

function useStore() {
  return useQuery({ queryKey: ["pub-store"], queryFn: async () => (await publicApi.get<{ data: StoreInfo }>("/store")).data.data, staleTime: 60_000 });
}

function OrderPage() {
  const params = useSearchParams();
  const router = useRouter();
  const token = params.get("t");
  const isTakeaway = !token;
  const ctx = token ?? "takeaway";
  const mounted = useMounted();

  const store = useStore();
  const menu = useQuery({
    queryKey: ["pub-menu"],
    queryFn: async () => (await publicApi.get<{ data: CatalogCategory[] }>("/menu")).data.data,
    staleTime: 60_000,
    refetchInterval: 120_000,
    refetchIntervalInBackground: false,
  });
  const table = useQuery({
    queryKey: ["pub-table", token],
    enabled: !!token,
    retry: false,
    queryFn: async () => (await publicApi.get<{ data: { code: string; name: string; area: string | null; open_bill: { tracking_key: string } | null } }>(`/tables/${token}`)).data.data,
  });

  const lines = useCart((s) => (mounted ? s.carts[ctx] : undefined)) ?? EMPTY_LINES;
  const trackingKey = useCart((s) => (mounted ? s.tracking[ctx] : undefined)) ?? table.data?.open_bill?.tracking_key;
  const { add, setQty, remove, clear, setTracking, customer, setCustomer } = useCart();

  const [search, setSearch] = useState("");
  const [activeCat, setActiveCat] = useState<number | null>(null);
  const [selected, setSelected] = useState<Menu | null>(null);
  const [cartOpen, setCartOpen] = useState(false);
  const [note, setNote] = useState("");
  const [honeypot, setHoneypot] = useState("");
  const [mode, setMode] = useState<OnlineMode>(params.get("mode") === "delivery" ? "delivery" : "takeaway");
  const [address, setAddress] = useState("");
  const [scheduled, setScheduled] = useState("");
  const catRefs = useRef<Record<number, HTMLElement | null>>({});

  const categories = useMemo(() => {
    const q = search.trim().toLowerCase();
    return (menu.data ?? [])
      .map((c) => ({ ...c, menus: q ? c.menus.filter((m) => m.name.toLowerCase().includes(q) || m.description?.toLowerCase().includes(q)) : c.menus }))
      .filter((c) => c.menus.length);
  }, [menu.data, search]);

  // Tandai kategori aktif saat scroll
  useEffect(() => {
    const obs = new IntersectionObserver(
      (entries) => {
        const vis = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (vis) setActiveCat(Number(vis.target.getAttribute("data-cat")));
      },
      { rootMargin: "-120px 0px -60% 0px" },
    );
    Object.values(catRefs.current).forEach((el) => el && obs.observe(el));
    return () => obs.disconnect();
  }, [categories]);

  const count = lines.reduce((s, l) => s + l.qty, 0);
  const subtotal = lines.reduce((s, l) => s + linePrice(l) * l.qty, 0);
  const st = store.data;
  const service = st?.service_enabled && !isTakeaway ? Math.round((subtotal * st.service_percent) / 100) : 0;
  const tax = st?.tax_enabled ? Math.round(((subtotal + service) * st.tax_percent) / 100) : 0;
  const isDelivery = isTakeaway && mode === "delivery" && !!st?.delivery_enabled;
  const deliveryFee = isDelivery ? (st?.delivery_fee ?? 0) : 0;
  const qtyInCart = (id: number) => lines.filter((l) => l.menu_id === id).reduce((s, l) => s + l.qty, 0);

  const submit = useMutation({
    mutationFn: async () => {
      const payload = {
        table_token: token ?? undefined,
        mode: isTakeaway ? (isDelivery ? "delivery" : "takeaway") : "dine_in",
        delivery_address: isDelivery ? address.trim() : undefined,
        scheduled_at: isTakeaway && scheduled ? scheduled : undefined,
        customer_name: customer.name.trim() || undefined,
        customer_phone: customer.phone.trim() || undefined,
        note: note.trim() || undefined,
        website: honeypot || undefined,
        items: lines.map((l) => ({ menu_id: l.menu_id, qty: l.qty, option_ids: l.options.map((o) => o.id), note: l.note || undefined })),
      };
      return (await publicApi.post("/orders", payload)).data as { data: { tracking_key: string }; message: string };
    },
    onSuccess: (res) => {
      toast.success(res.message);
      clear(ctx);
      setTracking(ctx, res.data.tracking_key);
      setCartOpen(false);
      router.push(`/lacak/?k=${res.data.tracking_key}${token ? `&t=${token}` : ""}`);
    },
    onError: (e) => {
      toast.error(errMsg(e, "Pesanan gagal dikirim."));
      void menu.refetch();
    },
  });

  if (token && table.isError) {
    return (
      <main className="flex min-h-screen items-center justify-center p-6">
        <EmptyState icon={<AlertCircle className="h-6 w-6" />} title="QR meja tidak valid" description="QR mungkin sudah diganti. Silakan minta bantuan pelayan kami." />
      </main>
    );
  }

  // online: toko tutup tetap bisa pre-order bila jadwal dipilih
  const closed = st && !st.is_open && !(isTakeaway && st.preorder_enabled && scheduled);
  const needContact = isTakeaway;
  const contactOk = !needContact || (customer.name.trim().length >= 2 && customer.phone.replace(/\D/g, "").length >= 9);
  const deliveryOk = !isDelivery || (address.trim().length >= 10 && subtotal >= (st?.delivery_min_order ?? 0));

  return (
    <div className="mx-auto min-h-screen max-w-2xl pb-28">
      {/* Header */}
      <header className="bg-gradient-to-b from-primary-soft to-bg px-4 pb-3 pt-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-3">
            {st?.cafe_logo ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={st.cafe_logo} alt="" className="h-11 w-11 rounded-2xl object-cover" />
            ) : (
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary text-xl text-primary-fg">â˜•</span>
            )}
            <div>
              <h1 className="text-lg font-bold leading-tight">{st?.cafe_name ?? <Skeleton className="h-5 w-32" />}</h1>
              <p className="flex items-center gap-1 text-sm text-muted">
                {isTakeaway ? (
                  <>
                    <ShoppingBag className="h-3.5 w-3.5" /> Pesan online · takeaway{st?.delivery_enabled ? " / antar" : ""}
                  </>
                ) : (
                  <>
                    <MapPin className="h-3.5 w-3.5" /> {table.data ? `${table.data.name}${table.data.area ? ` Â· ${table.data.area}` : ""}` : "Memuat meja..."}
                  </>
                )}
              </p>
            </div>
          </div>
          <ThemeToggle />
        </div>
        {trackingKey && (
          <Link href={`/lacak/?k=${trackingKey}${token ? `&t=${token}` : ""}`} className="mt-3 flex items-center justify-between rounded-2xl border border-primary/30 bg-surface px-4 py-2.5 text-sm font-medium">
            <span className="flex items-center gap-2">
              <ClipboardList className="h-4 w-4 text-primary" /> Lihat status pesanan Anda
            </span>
            <ChevronRight className="h-4 w-4 text-muted" />
          </Link>
        )}
        {closed && (
          <div className="mt-3 flex items-start gap-2 rounded-2xl bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /> {st?.closed_message}
          </div>
        )}
      </header>

      {/* Kategori sticky + cari */}
      <div className="sticky top-0 z-20 space-y-2 border-b border-border bg-bg/95 px-4 pb-2 pt-2 backdrop-blur">
        <SearchInput value={search} onChange={setSearch} placeholder="Cari menu..." />
        <div className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4" role="tablist" aria-label="Kategori menu">
          {(menu.data ?? []).map((c) => (
            <button
              key={c.id}
              role="tab"
              aria-selected={activeCat === c.id}
              onClick={() => catRefs.current[c.id]?.scrollIntoView({ behavior: "smooth", block: "start" })}
              className={cn(
                "shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition",
                activeCat === c.id ? "border-primary bg-primary text-primary-fg" : "border-border bg-surface text-muted",
              )}
            >
              {c.name}
            </button>
          ))}
        </div>
      </div>

      {/* Daftar menu */}
      <div className="space-y-6 px-4 pt-4">
        {menu.isLoading &&
          Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-28 w-full rounded-2xl" />)}
        {!menu.isLoading && categories.length === 0 && <EmptyState icon={<UtensilsCrossed className="h-6 w-6" />} title="Menu tidak ditemukan" description="Coba kata kunci lain." />}
        {categories.map((c) => (
          <section key={c.id} data-cat={c.id} ref={(el) => { catRefs.current[c.id] = el; }} className="scroll-mt-28">
            <h2 className="mb-2 text-base font-bold">{c.name}</h2>
            <div className="grid gap-2.5 sm:grid-cols-2">
              {c.menus.map((m) => (
                <MenuCard key={m.id} menu={m} onSelect={setSelected} inCart={qtyInCart(m.id)} />
              ))}
            </div>
          </section>
        ))}
      </div>

      {/* Keranjang melayang */}
      <AnimatePresence>
        {count > 0 && (
          <motion.div initial={{ y: 100 }} animate={{ y: 0 }} exit={{ y: 100 }} className="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-2xl p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <button onClick={() => setCartOpen(true)} className="flex w-full items-center justify-between rounded-2xl bg-primary px-5 py-3.5 text-primary-fg shadow-xl">
              <span className="flex items-center gap-3">
                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm font-bold">{count}</span>
                <span className="font-semibold">Lihat keranjang</span>
              </span>
              <span className="font-bold">{rp(subtotal)}</span>
            </button>
          </motion.div>
        )}
      </AnimatePresence>

      <ItemSheet
        menu={selected}
        onClose={() => setSelected(null)}
        maxQty={st?.max_qty_per_item ?? 20}
        onAdd={({ menu: m, options, qty, note: n }) => {
          add(ctx, { menu_id: m.id, name: m.name, image_url: m.image_url, base_price: m.price, options, qty, note: n || undefined });
          setSelected(null);
          toast.success(`${m.name} ditambahkan`, { duration: 1500 });
        }}
      />

      {/* Ringkasan keranjang */}
      <Modal
        open={cartOpen}
        onClose={() => setCartOpen(false)}
        title="Pesanan Anda"
        description={isTakeaway ? (isDelivery ? "Pesan antar" : "Takeaway") : table.data?.name}
        footer={
          <div className="w-full space-y-2">
            <Button size="lg" className="w-full" disabled={!count || !!closed || !contactOk || !deliveryOk} loading={submit.isPending} onClick={() => submit.mutate()}>
              {closed ? "Toko sedang tutup" : !contactOk ? "Isi nama & nomor WA" : `Kirim pesanan Â· ${rp(subtotal + service + tax)}`}
            </Button>
            <p className="text-center text-[11px] text-muted">Total final dihitung oleh sistem kasir.</p>
          </div>
        }
      >
        <ul className="divide-y divide-border">
          {lines.map((l) => (
            <li key={l.key} className="flex gap-3 py-3">
              <div className="min-w-0 flex-1">
                <p className="font-semibold">{l.name}</p>
                {l.options.length > 0 && <p className="text-xs text-muted">{l.options.map((o) => o.name).join(", ")}</p>}
                {l.note && <p className="text-xs italic text-muted">â€œ{l.note}â€</p>}
                <p className="mt-1 text-sm font-semibold text-primary">{rp(linePrice(l) * l.qty)}</p>
              </div>
              <div className="flex flex-col items-end gap-2">
                <QtyStepper size="sm" value={l.qty} onChange={(q) => setQty(ctx, l.key, q)} max={st?.max_qty_per_item ?? 20} />
                <button onClick={() => remove(ctx, l.key)} className="flex items-center gap-1 text-xs text-danger" aria-label={`Hapus ${l.name}`}>
                  <Trash2 className="h-3.5 w-3.5" /> Hapus
                </button>
              </div>
            </li>
          ))}
        </ul>

        <div className="mt-3 space-y-3">
          <div className="grid grid-cols-2 gap-2">
            <Field label={`Nama${needContact ? " *" : ""}`} htmlFor="c-name">
              <Input id="c-name" autoComplete="name" maxLength={60} value={customer.name} onChange={(e) => setCustomer({ ...customer, name: e.target.value })} placeholder="Nama Anda" />
            </Field>
            <Field label={`No. WhatsApp${needContact ? " *" : ""}`} htmlFor="c-phone">
              <Input id="c-phone" type="tel" inputMode="tel" autoComplete="tel" maxLength={20} value={customer.phone} onChange={(e) => setCustomer({ ...customer, phone: e.target.value })} placeholder="08xxxxxxxxxx" />
            </Field>
          </div>
          {isTakeaway && st && <OnlineOptions st={st} mode={mode} setMode={setMode} address={address} setAddress={setAddress} scheduled={scheduled} setScheduled={setScheduled} subtotal={subtotal} />}
          <Field label="Catatan pesanan" htmlFor="c-note">
            <Textarea id="c-note" maxLength={200} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Opsional" className="min-h-[56px]" />
          </Field>
          {/* honeypot anti-spam: disembunyikan dari manusia */}
          <input type="text" name="website" tabIndex={-1} autoComplete="off" value={honeypot} onChange={(e) => setHoneypot(e.target.value)} className="absolute left-[-9999px] h-0 w-0 opacity-0" aria-hidden />
          <dl className="space-y-1 rounded-2xl bg-surface-2 p-3 text-sm">
            <div className="flex justify-between"><dt className="text-muted">Subtotal</dt><dd>{rp(subtotal)}</dd></div>
            {service > 0 && <div className="flex justify-between"><dt className="text-muted">Service {st?.service_percent}%</dt><dd>{rp(service)}</dd></div>}
            {tax > 0 && <div className="flex justify-between"><dt className="text-muted">Pajak {st?.tax_percent}%</dt><dd>{rp(tax)}</dd></div>}
            {deliveryFee > 0 && <div className="flex justify-between"><dt className="text-muted">Ongkir</dt><dd>{rp(deliveryFee)}</dd></div>}
            <div className="flex justify-between border-t border-border pt-1 font-bold"><dt>Estimasi total</dt><dd>{rp(subtotal + service + tax + deliveryFee)}</dd></div>
          </dl>
        </div>
      </Modal>
    </div>
  );
}

export default function PesanPage() {
  return (
    <Suspense fallback={<div className="mx-auto max-w-2xl space-y-3 p-4">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-28" />)}</div>}>
      <OrderPage />
    </Suspense>
  );
}

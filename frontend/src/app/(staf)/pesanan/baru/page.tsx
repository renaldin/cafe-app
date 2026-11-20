"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { Bike, ShoppingBag, Trash2, Utensils } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useMemo, useState } from "react";
import { toast } from "sonner";
import { ItemSheet } from "@/components/customer/item-sheet";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, EmptyState, Skeleton } from "@/components/ui/card";
import { Field, Input, MoneyInput, Select, Textarea } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { QtyStepper } from "@/components/ui/qty";
import { SearchInput } from "@/components/ui/search-input";
import { api, errMsg } from "@/lib/api";
import { cn } from "@/lib/cn";
import { rp } from "@/lib/format";
import { orderable } from "@/lib/menu-utils";
import { lineKey, linePrice, type CartLine } from "@/stores/cart";
import type { Bill, Menu, TableRow } from "@/types";

function ManualOrder() {
  const params = useSearchParams();
  const router = useRouter();
  const billId = params.get("bill");
  const presetTable = params.get("table");
  const [type, setType] = useState<"dine_in" | "takeaway" | "delivery">("dine_in");
  const [tableId, setTableId] = useState<string>(presetTable ?? "");
  const [cart, setCart] = useState<CartLine[]>([]);
  const [selected, setSelected] = useState<Menu | null>(null);
  const [search, setSearch] = useState("");
  const [cat, setCat] = useState<number | "all">("all");
  const [info, setInfo] = useState({ customer_name: "", customer_phone: "", delivery_address: "", delivery_fee: 0, guest_count: 0, note: "" });

  const menus = useQuery({ queryKey: ["menus-all"], queryFn: async () => (await api.get<{ data: Menu[] }>("/menus", { params: { all: 1 } })).data.data });
  const tables = useQuery({ queryKey: ["tables-lite"], enabled: !billId, queryFn: async () => (await api.get<{ data: TableRow[] }>("/tables")).data.data });
  const bill = useQuery({ queryKey: ["bill", billId], enabled: !!billId, queryFn: async () => (await api.get<{ data: Bill }>(`/bills/${billId}`)).data.data });

  const cats = useMemo(() => {
    const m = new Map<number, string>();
    (menus.data ?? []).forEach((x) => x.category && m.set(x.category.id, x.category.name));
    return [...m.entries()];
  }, [menus.data]);
  const list = (menus.data ?? []).filter((m) => m.is_active && (cat === "all" || m.category_id === cat) && m.name.toLowerCase().includes(search.toLowerCase()));
  const subtotal = cart.reduce((s, l) => s + linePrice(l) * l.qty, 0);

  const submit = useMutation({
    mutationFn: async () => {
      const body: Record<string, unknown> = {
        items: cart.map((l) => ({ menu_id: l.menu_id, qty: l.qty, option_ids: l.options.map((o) => o.id), note: l.note })),
        note: info.note || undefined,
        customer_name: info.customer_name || undefined,
        customer_phone: info.customer_phone || undefined,
      };
      if (billId) body.bill_id = Number(billId);
      else {
        body.type = type;
        if (type === "dine_in") body.table_id = Number(tableId);
        if (type === "delivery") Object.assign(body, { delivery_address: info.delivery_address, delivery_fee: info.delivery_fee });
        if (info.guest_count) body.guest_count = info.guest_count;
      }
      return (await api.post("/orders", body)).data;
    },
    onSuccess: (r) => {
      toast.success(r.message);
      router.push(`/kasir/bill/?id=${r.data.bill_id}`);
    },
    onError: (e) => toast.error(errMsg(e)),
  });

  const tableList = Array.isArray(tables.data) ? tables.data : [];
  const canSubmit = cart.length > 0 && (billId || type !== "dine_in" || tableId) && (type !== "delivery" || info.delivery_address.length > 5);

  return (
    <div>
      <PageHeader title={billId ? `Tambah item · ${bill.data?.label ?? ""}` : "Pesanan manual"} description="Untuk pelayan & kasir: dine-in, takeaway, atau delivery." />
      <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
        <Card className="p-4">
          <div className="mb-3 flex flex-col gap-2 sm:flex-row">
            <SearchInput value={search} onChange={setSearch} placeholder="Cari menu..." className="flex-1" />
            <Select value={String(cat)} onChange={(e) => setCat(e.target.value === "all" ? "all" : Number(e.target.value))} className="sm:w-48" aria-label="Kategori">
              <option value="all">Semua kategori</option>
              {cats.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
            </Select>
          </div>
          {menus.isLoading ? (
            <div className="grid grid-cols-2 gap-2 md:grid-cols-3">{Array.from({ length: 9 }).map((_, i) => <Skeleton key={i} className="h-20" />)}</div>
          ) : (
            <div className="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-4">
              {list.map((m) => {
                const ok = orderable(m);
                return (
                  <button key={m.id} disabled={!ok} onClick={() => setSelected(m)} className={cn("rounded-xl border border-border p-3 text-left transition hover:border-primary hover:bg-primary-soft", !ok && "opacity-40")}>
                    <p className="line-clamp-2 text-sm font-semibold">{m.name}</p>
                    <p className="mt-1 text-sm text-primary">{rp(m.price)}</p>
                    {!m.is_available && <p className="text-xs text-danger">Habis</p>}
                  </button>
                );
              })}
            </div>
          )}
        </Card>

        <div className="space-y-4 lg:sticky lg:top-20 lg:self-start">
          {!billId && (
            <Card className="space-y-3 p-4">
              <div className="grid grid-cols-3 gap-1.5">
                {([["dine_in", "Dine-in", Utensils], ["takeaway", "Takeaway", ShoppingBag], ["delivery", "Delivery", Bike]] as const).map(([v, l, Icon]) => (
                  <button key={v} onClick={() => setType(v)} className={cn("flex flex-col items-center gap-1 rounded-xl border py-2 text-xs font-semibold", type === v ? "border-primary bg-primary-soft text-primary" : "border-border text-muted")}>
                    <Icon className="h-4 w-4" />{l}
                  </button>
                ))}
              </div>
              {type === "dine_in" && (
                <Field label="Meja">
                  <Select value={tableId} onChange={(e) => setTableId(e.target.value)}>
                    <option value="">— Pilih meja —</option>
                    {tableList.filter((t) => t.is_active).map((t) => <option key={t.id} value={t.id}>{t.code} · {t.status_label}{t.open_bill ? " (tambah ke bill)" : ""}</option>)}
                  </Select>
                </Field>
              )}
              <div className="grid grid-cols-2 gap-2">
                <Field label="Nama"><Input value={info.customer_name} onChange={(e) => setInfo({ ...info, customer_name: e.target.value })} /></Field>
                <Field label="No. WA"><Input type="tel" value={info.customer_phone} onChange={(e) => setInfo({ ...info, customer_phone: e.target.value })} /></Field>
              </div>
              {type === "dine_in" && <Field label="Jumlah tamu"><Input type="number" min={1} value={info.guest_count || ""} onChange={(e) => setInfo({ ...info, guest_count: Number(e.target.value) })} /></Field>}
              {type === "delivery" && (
                <>
                  <Field label="Alamat antar *"><Textarea value={info.delivery_address} onChange={(e) => setInfo({ ...info, delivery_address: e.target.value })} /></Field>
                  <Field label="Ongkir"><MoneyInput value={info.delivery_fee} onChange={(v) => setInfo({ ...info, delivery_fee: v })} /></Field>
                </>
              )}
            </Card>
          )}

          <Card>
            <CardHeader title={`Keranjang (${cart.reduce((s, l) => s + l.qty, 0)})`} />
            {cart.length === 0 ? (
              <EmptyState title="Belum ada item" description="Pilih menu di sebelah kiri." />
            ) : (
              <ul className="divide-y divide-border">
                {cart.map((l) => (
                  <li key={l.key} className="flex items-start gap-2 px-4 py-2.5">
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-semibold">{l.name}</p>
                      {l.options.length > 0 && <p className="text-xs text-muted">{l.options.map((o) => o.name).join(", ")}</p>}
                      {l.note && <p className="text-xs italic text-muted">“{l.note}”</p>}
                      <p className="text-sm text-primary">{rp(linePrice(l) * l.qty)}</p>
                    </div>
                    <QtyStepper size="sm" value={l.qty} onChange={(q) => setCart((c) => c.map((x) => (x.key === l.key ? { ...x, qty: q } : x)).filter((x) => x.qty > 0))} max={100} />
                    <button onClick={() => setCart((c) => c.filter((x) => x.key !== l.key))} className="p-1 text-danger" aria-label="Hapus"><Trash2 className="h-4 w-4" /></button>
                  </li>
                ))}
              </ul>
            )}
            <div className="space-y-2 border-t border-border p-4">
              <Field label="Catatan pesanan"><Input value={info.note} onChange={(e) => setInfo({ ...info, note: e.target.value })} /></Field>
              <div className="flex justify-between font-bold"><span>Subtotal</span><span>{rp(subtotal)}</span></div>
              <Button size="lg" className="w-full" disabled={!canSubmit} loading={submit.isPending} onClick={() => submit.mutate()}>
                Kirim ke dapur
              </Button>
            </div>
          </Card>
        </div>
      </div>
      <ItemSheet
        menu={selected}
        onClose={() => setSelected(null)}
        maxQty={100}
        onAdd={({ menu, options, qty, note }) => {
          const key = lineKey(menu.id, options.map((o) => o.id), note);
          setCart((c) => {
            const f = c.find((x) => x.key === key);
            if (f) return c.map((x) => (x.key === key ? { ...x, qty: x.qty + qty } : x));
            return [...c, { key, menu_id: menu.id, name: menu.name, base_price: menu.price, options, qty, note: note || undefined }];
          });
          setSelected(null);
        }}
      />
    </div>
  );
}

export default function PesananBaruPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96" />}>
      <ManualOrder />
    </Suspense>
  );
}

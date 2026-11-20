"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeftRight, Ban, Gift, Merge, MessageCircle, Percent, Plus, Printer, Receipt as ReceiptIcon, RotateCcw, Scissors, Ticket, Trash2, UserPlus, Wallet } from "lucide-react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { toast } from "sonner";
import { PaymentPanel } from "@/components/staff/payment-panel";
import { receiptText, type ReceiptStore } from "@/components/staff/receipt";
import { ShiftBanner } from "@/components/staff/shift-gate";
import { Button } from "@/components/ui/button";
import { Badge, Card, CardHeader, EmptyState, Skeleton } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { Checkbox, Field, Input, MoneyInput, Select } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { QtyStepper } from "@/components/ui/qty";
import { api, errMsg } from "@/lib/api";
import { cn } from "@/lib/cn";
import { BILL_STATUS, ITEM_STATUS, PAY_METHOD, fdatetime, ftime, rp, waNumber } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Bill, TableRow } from "@/types";

function BillDetail() {
  const id = useSearchParams().get("id");
  const can = useCan();
  const qc = useQueryClient();
  const key = ["bill", id];
  const q = useQuery({
    queryKey: key,
    enabled: !!id,
    queryFn: async () => (await api.get<{ data: Bill }>(`/bills/${id}`)).data.data,
    refetchInterval: 8000,
    refetchIntervalInBackground: false,
  });
  const set = (b: Bill) => qc.setQueryData(key, b);
  const [modal, setModal] = useState<null | "discount" | "split" | "merge" | "move" | "refund" | "customer">(null);

  const act = useMutation({
    mutationFn: async ({ url, body }: { url: string; body?: unknown }) => (await api.post(url, body)).data,
    onSuccess: (r) => {
      toast.success(r.message);
      if (r.data?.id) set(r.data);
      else if (r.data?.bill) set(r.data.bill);
      setModal(null);
      void qc.invalidateQueries({ queryKey: ["feed"] });
    },
    onError: (e) => toast.error(errMsg(e)),
  });
  const removeItem = useMutation({
    mutationFn: async ({ itemId, reason }: { itemId: number; reason?: string }) => (await api.delete(`/order-items/${itemId}`, { data: { reason } })).data,
    onSuccess: (r) => { toast.success(r.message); set(r.data); },
    onError: (e) => toast.error(errMsg(e)),
  });

  const receipt = useQuery({
    queryKey: ["receipt-store"],
    queryFn: async () => (await api.get<{ data: { store: ReceiptStore } }>(`/bills/${id}/receipt`)).data.data.store,
    enabled: !!id,
    staleTime: 300_000,
  });

  if (!id) return <EmptyState title="Bill tidak dipilih" />;
  if (q.isLoading) return <div className="grid gap-4 lg:grid-cols-[1fr_400px]"><Skeleton className="h-96" /><Skeleton className="h-96" /></div>;
  if (!q.data) return <EmptyState title="Bill tidak ditemukan" />;
  const b = q.data;
  const open = b.status === "open" || b.status === "awaiting_payment";
  const items = b.items ?? [];
  const disc = b.promo_amount + b.discount_amount + b.voucher_amount + b.points_amount;

  const onRemove = async (itemId: number, name: string, inKitchen: boolean) => {
    const r = await confirm({ title: `Hapus ${name}?`, description: inKitchen ? "Item sudah masuk dapur. Penghapusan dicatat di log aktivitas." : undefined, danger: true, reason: inKitchen ? "Alasan penghapusan" : false, confirmText: "Hapus item" });
    if (r.ok) removeItem.mutate({ itemId, reason: r.reason });
  };
  const sendWa = () => {
    const wa = waNumber(b.customer_phone ?? b.customer?.phone);
    if (!receipt.data) return;
    const text = encodeURIComponent(receiptText(b, receipt.data));
    window.open(wa ? `https://wa.me/${wa}?text=${text}` : `https://wa.me/?text=${text}`, "_blank", "noopener");
  };

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-bold">{b.label}</h1>
            <Badge className={BILL_STATUS[b.status]?.className}>{BILL_STATUS[b.status]?.text}</Badge>
            <Badge className="bg-surface-2 text-muted">{b.type_label}</Badge>
          </div>
          <p className="text-sm text-muted">{b.invoice_no} · dibuka {fdatetime(b.opened_at)}{b.opened_by ? ` oleh ${b.opened_by}` : ""}</p>
          {b.parent_bill_id && <Link href={`/kasir/bill/?id=${b.parent_bill_id}`} className="text-sm text-primary">← Bill asal (split)</Link>}
        </div>
        <div className="flex flex-wrap gap-2">
          <Link href={`/struk/?id=${b.id}`} target="_blank"><Button variant="outline" size="sm"><Printer className="h-4 w-4" /> Cetak struk</Button></Link>
          <Button variant="outline" size="sm" onClick={sendWa}><MessageCircle className="h-4 w-4" /> Kirim WA</Button>
        </div>
      </div>

      {open && <ShiftBanner />}

      <div className="grid gap-4 lg:grid-cols-[1fr_420px]">
        <div className="space-y-4">
          <Card>
            <CardHeader
              title={`Item (${items.filter((i) => i.status !== "cancelled").reduce((s, i) => s + i.qty, 0)})`}
              action={open && can("order.create") && <Link href={`/pesanan/baru/?bill=${b.id}`}><Button size="sm"><Plus className="h-4 w-4" /> Tambah item</Button></Link>}
            />
            <ul className="divide-y divide-border">
              {items.map((i) => {
                const order = b.orders?.find((o) => o.id === i.order_id);
                const inKitchen = order?.status === "confirmed";
                return (
                  <li key={i.id} className={cn("flex items-start gap-3 px-5 py-3", i.status === "cancelled" && "opacity-50")}>
                    <span className="flex h-8 min-w-8 items-center justify-center rounded-lg bg-surface-2 font-bold">{i.qty}</span>
                    <div className="min-w-0 flex-1">
                      <p className={cn("font-medium", i.status === "cancelled" && "line-through")}>{i.name}</p>
                      {i.modifiers && i.modifiers.length > 0 && <p className="text-xs text-muted">{i.modifiers.map((m) => `${m.name}${m.price_delta ? ` (+${rp(m.price_delta)})` : ""}`).join(", ")}</p>}
                      {i.note && <p className="text-xs italic text-muted">“{i.note}”</p>}
                      {i.cancel_reason && <p className="text-xs text-danger">Batal: {i.cancel_reason}</p>}
                      <div className="mt-1 flex items-center gap-2">
                        <Badge className={ITEM_STATUS[i.status]?.className}>{ITEM_STATUS[i.status]?.text}</Badge>
                        <span className="text-xs text-muted">#{order?.order_no} · {ftime(i.created_at)}</span>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="font-semibold">{rp(i.line_total)}</p>
                      {open && i.status !== "cancelled" && can("bill.manage") && b.paid_amount === 0 && (
                        <button onClick={() => onRemove(i.id, i.name, inKitchen)} className="mt-1 inline-flex items-center gap-1 text-xs text-danger hover:underline">
                          <Trash2 className="h-3 w-3" /> Hapus
                        </button>
                      )}
                    </div>
                  </li>
                );
              })}
            </ul>
          </Card>

          {open && (
            <Card className="p-4">
              <p className="mb-3 text-sm font-semibold text-muted">Aksi bill</p>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {b.status === "open" && <Button variant="outline" onClick={() => act.mutate({ url: `/bills/${b.id}/request-bill` })}><ReceiptIcon className="h-4 w-4" /> Minta bill</Button>}
                {can("bill.manage") && <Button variant="outline" onClick={() => setModal("customer")}><UserPlus className="h-4 w-4" /> Pelanggan</Button>}
                {can("bill.discount") && <Button variant="outline" onClick={() => setModal("discount")}><Percent className="h-4 w-4" /> Diskon</Button>}
                {can("bill.manage") && <Button variant="outline" onClick={() => setModal("split")}><Scissors className="h-4 w-4" /> Pecah bill</Button>}
                {can("bill.manage") && <Button variant="outline" onClick={() => setModal("merge")}><Merge className="h-4 w-4" /> Gabung</Button>}
                {can("bill.manage") && b.type === "dine_in" && <Button variant="outline" onClick={() => setModal("move")}><ArrowLeftRight className="h-4 w-4" /> Pindah meja</Button>}
                {can("bill.void") && (
                  <Button variant="outline" className="text-danger" onClick={async () => { const r = await confirm({ title: "Void bill ini?", description: "Semua item dibatalkan dan stok dikembalikan bila sudah dipotong.", danger: true, reason: true, confirmText: "Void" }); if (r.ok) act.mutate({ url: `/bills/${b.id}/void`, body: { reason: r.reason } }); }}>
                    <Ban className="h-4 w-4" /> Void
                  </Button>
                )}
              </div>
              {can("bill.manage") && <VoucherBox bill={b} onDone={set} />}
            </Card>
          )}

          {(b.payments?.length ?? 0) > 0 && (
            <Card>
              <CardHeader title="Pembayaran" />
              <ul className="divide-y divide-border text-sm">
                {b.payments!.map((p) => (
                  <li key={p.id} className="flex justify-between px-5 py-2.5">
                    <span>{PAY_METHOD[p.method]} {p.reference && <span className="text-muted">· {p.reference}</span>} <span className="text-xs text-muted">{ftime(p.paid_at)}</span> {p.status !== "paid" && <Badge className="bg-surface-2">{p.status}</Badge>}</span>
                    <span className="font-semibold">{rp(p.amount)}{p.change > 0 && <span className="ml-1 text-xs text-muted">(kembali {rp(p.change)})</span>}</span>
                  </li>
                ))}
                {b.refunds?.map((r) => (
                  <li key={r.id} className="flex justify-between px-5 py-2.5 text-danger">
                    <span>Refund · {r.reason}</span>
                    <span className="font-semibold">-{rp(r.amount)}</span>
                  </li>
                ))}
              </ul>
            </Card>
          )}
        </div>

        <div className="space-y-4">
          <Card className="p-5">
            <dl className="space-y-1.5 text-sm">
              <div className="flex justify-between"><dt className="text-muted">Subtotal</dt><dd>{rp(b.subtotal)}</dd></div>
              {b.promo_amount > 0 && <div className="flex justify-between text-success"><dt>Happy hour</dt><dd>-{rp(b.promo_amount)}</dd></div>}
              {b.discount_amount > 0 && <div className="flex justify-between text-success"><dt>Diskon {b.discount_type === "percent" ? `${b.discount_value}%` : ""}</dt><dd>-{rp(b.discount_amount)}</dd></div>}
              {b.voucher_amount > 0 && <div className="flex justify-between text-success"><dt>Voucher {b.voucher?.code}</dt><dd>-{rp(b.voucher_amount)}</dd></div>}
              {b.points_amount > 0 && <div className="flex justify-between text-success"><dt>Tukar {b.points_redeemed} poin</dt><dd>-{rp(b.points_amount)}</dd></div>}
              {b.service_charge > 0 && <div className="flex justify-between"><dt className="text-muted">Service charge</dt><dd>{rp(b.service_charge)}</dd></div>}
              {b.tax > 0 && <div className="flex justify-between"><dt className="text-muted">Pajak (PPN)</dt><dd>{rp(b.tax)}</dd></div>}
              {b.delivery_fee > 0 && <div className="flex justify-between"><dt className="text-muted">Ongkir</dt><dd>{rp(b.delivery_fee)}</dd></div>}
              {b.rounding !== 0 && <div className="flex justify-between"><dt className="text-muted">Pembulatan</dt><dd>{rp(b.rounding)}</dd></div>}
              <div className="flex justify-between border-t border-border pt-2 text-xl font-black"><dt>Total</dt><dd>{rp(b.total)}</dd></div>
              {b.paid_amount > 0 && <div className="flex justify-between text-success"><dt>Dibayar</dt><dd>{rp(b.paid_amount)}</dd></div>}
              {disc > 0 && <p className="text-xs text-success">Hemat {rp(disc)}</p>}
            </dl>
            {(b.customer || b.customer_name) && (
              <div className="mt-3 rounded-xl bg-surface-2 px-3 py-2 text-sm">
                <p className="font-semibold">{b.customer?.name ?? b.customer_name}</p>
                <p className="text-muted">{b.customer?.phone ?? b.customer_phone}{b.customer ? ` · ${b.customer.points} poin` : ""}</p>
              </div>
            )}
            {b.scheduled_at && <p className="mt-2 rounded-xl bg-violet-100 px-3 py-2 text-sm font-semibold text-violet-800 dark:bg-violet-500/15 dark:text-violet-200">⏰ Pre-order: {fdatetime(b.scheduled_at)}</p>}
            {b.delivery_address && <p className="mt-2 rounded-xl bg-surface-2 px-3 py-2 text-sm">📍 {b.delivery_address}</p>}
            {b.type === "delivery" && open && can("bill.manage") && b.paid_amount === 0 && <DeliveryFeeBox bill={b} onDone={set} />}
          </Card>

          {open && can("payment.process") && b.balance_due > 0 && (
            <Card className="p-5">
              <h3 className="mb-3 flex items-center gap-2 font-semibold"><Wallet className="h-4 w-4" /> Pembayaran</h3>
              <PaymentPanel bill={b} onPaid={(nb) => { set(nb); void qc.invalidateQueries({ queryKey: ["shift-current"] }); if (nb.status === "paid") window.open(`/struk/?id=${nb.id}`, "_blank", "noopener"); }} />
            </Card>
          )}

          {b.status === "paid" && (
            <Card className="p-5 text-center">
              <p className="text-lg font-bold text-success">Bill lunas</p>
              <p className="text-sm text-muted">{fdatetime(b.paid_at)} {b.closed_by ? `· ${b.closed_by}` : ""}</p>
              {b.change_amount > 0 && <p className="mt-1">Kembalian: <b>{rp(b.change_amount)}</b></p>}
              {can("bill.refund") && <Button variant="outline" className="mt-3 text-danger" onClick={() => setModal("refund")}><RotateCcw className="h-4 w-4" /> Refund</Button>}
            </Card>
          )}
          {b.status === "void" && <Card className="p-5 text-center text-danger">Bill di-void: {b.void_reason}</Card>}
        </div>
      </div>

      <DiscountModal open={modal === "discount"} bill={b} onClose={() => setModal(null)} onSubmit={(body) => act.mutate({ url: `/bills/${b.id}/discount`, body })} loading={act.isPending} />
      <SplitModal open={modal === "split"} bill={b} onClose={() => setModal(null)} onSubmit={(body) => act.mutate({ url: `/bills/${b.id}/split`, body })} loading={act.isPending} />
      <MergeModal open={modal === "merge"} bill={b} onClose={() => setModal(null)} onSubmit={(body) => act.mutate({ url: `/bills/${b.id}/merge`, body })} loading={act.isPending} />
      <MoveModal open={modal === "move"} bill={b} onClose={() => setModal(null)} onSubmit={(body) => act.mutate({ url: `/bills/${b.id}/move-table`, body })} loading={act.isPending} />
      <RefundModal open={modal === "refund"} bill={b} onClose={() => setModal(null)} onSubmit={(body) => act.mutate({ url: `/bills/${b.id}/refund`, body })} loading={act.isPending} />
      <CustomerModal open={modal === "customer"} bill={b} onClose={() => setModal(null)} onSubmit={(body) => act.mutate({ url: `/bills/${b.id}/customer`, body })} loading={act.isPending} />
    </div>
  );
}

type MP = { open: boolean; bill: Bill; onClose: () => void; onSubmit: (body: unknown) => void; loading: boolean };

function VoucherBox({ bill, onDone }: { bill: Bill; onDone: (b: Bill) => void }) {
  const [code, setCode] = useState("");
  const [points, setPoints] = useState(0);
  const m = useMutation({
    mutationFn: async ({ url, body }: { url: string; body: unknown }) => (await api.post(url, body)).data,
    onSuccess: (r) => { toast.success(r.message); onDone(r.data); setCode(""); },
    onError: (e) => toast.error(errMsg(e)),
  });
  return (
    <div className="mt-4 grid gap-3 sm:grid-cols-2">
      <div className="flex gap-2">
        <Input value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} placeholder={bill.voucher ? `Voucher: ${bill.voucher.code}` : "Kode voucher"} aria-label="Kode voucher" />
        {bill.voucher ? (
          <Button variant="outline" onClick={() => m.mutate({ url: `/bills/${bill.id}/voucher`, body: { code: null } })}>Lepas</Button>
        ) : (
          <Button variant="secondary" disabled={!code} loading={m.isPending} onClick={() => m.mutate({ url: `/bills/${bill.id}/voucher`, body: { code } })}><Ticket className="h-4 w-4" /> Pakai</Button>
        )}
      </div>
      {bill.customer && bill.customer.points > 0 && (
        <div className="flex gap-2">
          <Input type="number" min={0} max={bill.customer.points} value={points || ""} onChange={(e) => setPoints(Number(e.target.value))} placeholder={`Poin (maks ${bill.customer.points})`} aria-label="Tukar poin" />
          <Button variant="secondary" loading={m.isPending} onClick={() => m.mutate({ url: `/bills/${bill.id}/points`, body: { points } })}><Gift className="h-4 w-4" /> Tukar</Button>
        </div>
      )}
    </div>
  );
}

function DeliveryFeeBox({ bill, onDone }: { bill: Bill; onDone: (b: Bill) => void }) {
  const [fee, setFee] = useState(bill.delivery_fee);
  const m = useMutation({
    mutationFn: async () => (await api.post(`/bills/${bill.id}/delivery`, { delivery_fee: fee })).data,
    onSuccess: (r) => { toast.success(r.message); onDone(r.data); },
    onError: (e) => toast.error(errMsg(e)),
  });
  return (
    <div className="mt-2 flex items-end gap-2">
      <Field label="Ongkir" className="flex-1"><MoneyInput value={fee} onChange={setFee} /></Field>
      <Button variant="secondary" disabled={fee === bill.delivery_fee} loading={m.isPending} onClick={() => m.mutate()}>Simpan</Button>
    </div>
  );
}

function DiscountModal({ open, bill, onClose, onSubmit, loading }: MP) {
  const [type, setType] = useState<"percent" | "fixed">(bill.discount_type ?? "percent");
  const [value, setValue] = useState(bill.discount_value || 0);
  const [reason, setReason] = useState("");
  return (
    <Modal open={open} onClose={onClose} title="Diskon manual" size="sm" footer={<>
      {bill.discount_type && <Button variant="outline" onClick={() => onSubmit({ type: null, value: 0 })}>Hapus diskon</Button>}
      <Button loading={loading} disabled={!value || reason.length < 3} onClick={() => onSubmit({ type, value, reason })}>Terapkan</Button>
    </>}>
      <div className="space-y-3">
        <div className="grid grid-cols-2 gap-2">
          <Button variant={type === "percent" ? "primary" : "outline"} onClick={() => setType("percent")}>Persen (%)</Button>
          <Button variant={type === "fixed" ? "primary" : "outline"} onClick={() => setType("fixed")}>Nominal (Rp)</Button>
        </div>
        <Field label={type === "percent" ? "Diskon (%)" : "Diskon (Rp)"}>
          {type === "percent" ? <Input type="number" min={0} max={100} value={value || ""} onChange={(e) => setValue(Math.min(100, Number(e.target.value)))} /> : <MoneyInput value={value} onChange={setValue} />}
        </Field>
        <Field label="Alasan *"><Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Contoh: pelanggan setia" /></Field>
      </div>
    </Modal>
  );
}

function SplitModal({ open, bill, onClose, onSubmit, loading }: MP) {
  const items = (bill.items ?? []).filter((i) => i.status !== "cancelled");
  const [sel, setSel] = useState<Record<number, number>>({});
  const total = items.reduce((s, i) => s + ((sel[i.id] ?? 0) * i.line_total) / i.qty, 0);
  return (
    <Modal open={open} onClose={onClose} title="Pecah bill per item" description="Pilih item yang dipindah ke bill baru. Untuk bagi rata per orang, gunakan opsi 'Bagi rata' di panel pembayaran." footer={<Button loading={loading} disabled={!Object.values(sel).some((v) => v > 0)} onClick={() => onSubmit({ items: Object.entries(sel).filter(([, q]) => q > 0).map(([id, qty]) => ({ item_id: Number(id), qty })) })}>Pindahkan ke bill baru · {rp(total)}</Button>}>
      <ul className="divide-y divide-border">
        {items.map((i) => (
          <li key={i.id} className="flex items-center justify-between gap-3 py-2.5">
            <div>
              <p className="font-medium">{i.name}</p>
              <p className="text-xs text-muted">{i.qty} × {rp(i.line_total / i.qty)}</p>
            </div>
            <QtyStepper size="sm" value={sel[i.id] ?? 0} max={i.qty} onChange={(v) => setSel((s) => ({ ...s, [i.id]: v }))} />
          </li>
        ))}
      </ul>
    </Modal>
  );
}

function MergeModal({ open, bill, onClose, onSubmit, loading }: MP) {
  const q = useQuery({ queryKey: ["open-bills"], enabled: open, queryFn: async () => (await api.get<{ data: Bill[] }>("/bills", { params: { status: "open,awaiting_payment", per_page: 100 } })).data.data });
  const [sel, setSel] = useState<number[]>([]);
  const list = (q.data ?? []).filter((x) => x.id !== bill.id && x.paid_amount === 0);
  return (
    <Modal open={open} onClose={onClose} title={`Gabung ke ${bill.label}`} description="Item dari bill terpilih akan dipindahkan ke bill ini." footer={<Button loading={loading} disabled={!sel.length} onClick={() => onSubmit({ bill_ids: sel })}>Gabungkan ({sel.length})</Button>}>
      {q.isLoading && <Skeleton className="h-24" />}
      {!q.isLoading && list.length === 0 && <p className="py-6 text-center text-sm text-muted">Tidak ada bill lain yang bisa digabung.</p>}
      <div className="space-y-1.5">
        {list.map((x) => (
          <div key={x.id} className="rounded-xl border border-border px-3 py-2">
            <Checkbox checked={sel.includes(x.id)} onChange={(v) => setSel((s) => (v ? [...s, x.id] : s.filter((y) => y !== x.id)))} label={<span><b>{x.label}</b> · {x.invoice_no} · {rp(x.total)}</span>} />
          </div>
        ))}
      </div>
    </Modal>
  );
}

function MoveModal({ open, onClose, onSubmit, loading }: MP) {
  const q = useQuery({ queryKey: ["tables-lite"], enabled: open, queryFn: async () => (await api.get<{ data: TableRow[] }>("/tables")).data.data });
  const [tableId, setTableId] = useState<number | null>(null);
  const empty = (Array.isArray(q.data) ? q.data : []).filter((t) => t.status === "empty" && t.is_active);
  return (
    <Modal open={open} onClose={onClose} title="Pindah meja" size="sm" footer={<Button loading={loading} disabled={!tableId} onClick={() => onSubmit({ table_id: tableId })}>Pindahkan</Button>}>
      <div className="grid grid-cols-3 gap-2">
        {empty.map((t) => (
          <button key={t.id} onClick={() => setTableId(t.id)} className={cn("rounded-xl border py-3 font-bold", tableId === t.id ? "border-primary bg-primary text-primary-fg" : "border-border")}>
            {t.code}
            <span className="block text-[11px] font-normal opacity-70">{t.area?.name}</span>
          </button>
        ))}
      </div>
      {empty.length === 0 && !q.isLoading && <p className="py-4 text-center text-sm text-muted">Tidak ada meja kosong. Gunakan fitur gabung bill.</p>}
    </Modal>
  );
}

function RefundModal({ open, bill, onClose, onSubmit, loading }: MP) {
  const refunded = (bill.refunds ?? []).reduce((s, r) => s + r.amount, 0);
  const [amount, setAmount] = useState(bill.total - refunded);
  const [reason, setReason] = useState("");
  const [restock, setRestock] = useState(false);
  const [method, setMethod] = useState("cash");
  return (
    <Modal open={open} onClose={onClose} title="Refund" size="sm" footer={<Button variant="danger" loading={loading} disabled={reason.length < 3 || amount < 1} onClick={() => onSubmit({ amount, reason, restock, method })}>Proses refund</Button>}>
      <div className="space-y-3">
        <Field label={`Nominal (maks ${rp(bill.total - refunded)})`}><MoneyInput value={amount} onChange={setAmount} /></Field>
        <Field label="Metode pengembalian">
          <Select value={method} onChange={(e) => setMethod(e.target.value)}>
            {Object.entries(PAY_METHOD).filter(([k]) => k !== "gateway").map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </Select>
        </Field>
        <Field label="Alasan *"><Input value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
        <Checkbox checked={restock} onChange={setRestock} label="Kembalikan stok bahan (item belum dibuat/tidak terpakai)" />
      </div>
    </Modal>
  );
}

function CustomerModal({ open, bill, onClose, onSubmit, loading }: MP) {
  const [name, setName] = useState(bill.customer_name ?? "");
  const [phone, setPhone] = useState(bill.customer_phone ?? "");
  return (
    <Modal open={open} onClose={onClose} title="Data pelanggan" description="Pelanggan dengan nomor WA terdaftar akan mendapatkan poin." size="sm" footer={<Button loading={loading} onClick={() => onSubmit({ name, phone })}>Simpan</Button>}>
      <div className="space-y-3">
        <Field label="Nomor WA"><Input type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="08xxxxxxxxxx" /></Field>
        <Field label="Nama"><Input value={name} onChange={(e) => setName(e.target.value)} /></Field>
      </div>
    </Modal>
  );
}

export default function BillPage() {
  return (
    <Suspense fallback={<Skeleton className="h-96" />}>
      <BillDetail />
    </Suspense>
  );
}

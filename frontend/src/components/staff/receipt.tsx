"use client";

import { ftime, fdate, rp, PAY_METHOD } from "@/lib/format";
import type { Bill } from "@/types";

export type ReceiptStore = { name: string; address: string | null; phone: string | null; logo: string | null; footer: string | null; paper: string; instagram: string | null; tax_percent: number; service_percent: number };

/** Struk thermal 58/80mm (dicetak via window.print) */
export function Receipt({ bill, store, cashier }: { bill: Bill; store: ReceiptStore; cashier?: string }) {
  const width = store.paper === "80" ? "80mm" : "58mm";
  const items = (bill.items ?? []).filter((i) => i.status !== "cancelled");
  const disc = bill.promo_amount + bill.discount_amount + bill.voucher_amount + bill.points_amount;
  return (
    <div className="receipt mx-auto bg-white p-2 font-mono text-[11px] leading-snug text-black" style={{ width }}>
      <div className="text-center">
        {store.logo && (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={store.logo} alt="" className="mx-auto mb-1 h-10 w-10 object-contain grayscale" />
        )}
        <p className="text-[13px] font-bold">{store.name}</p>
        {store.address && <p>{store.address}</p>}
        {store.phone && <p>Telp {store.phone}</p>}
      </div>
      <hr className="my-1.5 border-dashed border-black" />
      <div>
        <p>No : {bill.invoice_no}</p>
        <p>Tgl: {fdate(bill.paid_at ?? bill.opened_at, "dd/MM/yyyy")} {ftime(bill.paid_at ?? bill.opened_at)}</p>
        <p>{bill.label}{bill.customer_name ? ` · ${bill.customer_name}` : ""}</p>
        {cashier && <p>Kasir: {cashier}</p>}
      </div>
      <hr className="my-1.5 border-dashed border-black" />
      {items.map((i) => (
        <div key={i.id} className="mb-1">
          <p>{i.name}</p>
          {i.modifiers?.map((m) => <p key={m.name} className="pl-2">+ {m.name}</p>)}
          <div className="flex justify-between pl-2">
            <span>{i.qty} x {rp(i.line_total / i.qty).replace("Rp ", "")}</span>
            <span>{rp(i.line_total).replace("Rp ", "")}</span>
          </div>
        </div>
      ))}
      <hr className="my-1.5 border-dashed border-black" />
      <Row l="Subtotal" r={bill.subtotal} />
      {disc > 0 && <Row l="Diskon" r={-disc} />}
      {bill.service_charge > 0 && <Row l={`Service ${store.service_percent}%`} r={bill.service_charge} />}
      {bill.tax > 0 && <Row l={`PPN ${store.tax_percent}%`} r={bill.tax} />}
      {bill.delivery_fee > 0 && <Row l="Ongkir" r={bill.delivery_fee} />}
      {bill.rounding !== 0 && <Row l="Pembulatan" r={bill.rounding} />}
      <div className="flex justify-between text-[13px] font-bold">
        <span>TOTAL</span>
        <span>{rp(bill.total)}</span>
      </div>
      {(bill.payments ?? []).filter((p) => p.status === "paid").map((p) => (
        <div key={p.id}>
          <Row l={PAY_METHOD[p.method] ?? p.method} r={p.method === "cash" ? p.received : p.amount} />
          {p.change > 0 && <Row l="Kembali" r={p.change} />}
        </div>
      ))}
      <hr className="my-1.5 border-dashed border-black" />
      <p className="text-center">{bill.status === "paid" ? "LUNAS" : "BELUM DIBAYAR"}</p>
      {store.footer && <p className="mt-1 whitespace-pre-line text-center">{store.footer}</p>}
      {store.instagram && <p className="text-center">IG: {store.instagram}</p>}
    </div>
  );
}

function Row({ l, r }: { l: string; r: number }) {
  return (
    <div className="flex justify-between">
      <span>{l}</span>
      <span>{rp(r)}</span>
    </div>
  );
}

/** Teks struk untuk dikirim via WhatsApp */
export function receiptText(bill: Bill, store: ReceiptStore): string {
  const lines = [
    `*${store.name}*`,
    `No: ${bill.invoice_no}`,
    `${fdate(bill.paid_at ?? bill.opened_at, "dd/MM/yyyy HH:mm")}`,
    "",
    ...(bill.items ?? []).filter((i) => i.status !== "cancelled").map((i) => `${i.qty}x ${i.name}${i.modifiers?.length ? ` (${i.modifiers.map((m) => m.name).join(", ")})` : ""} — ${rp(i.line_total)}`),
    "",
    `Subtotal: ${rp(bill.subtotal)}`,
  ];
  const disc = bill.promo_amount + bill.discount_amount + bill.voucher_amount + bill.points_amount;
  if (disc) lines.push(`Diskon: -${rp(disc)}`);
  if (bill.service_charge) lines.push(`Service: ${rp(bill.service_charge)}`);
  if (bill.tax) lines.push(`Pajak: ${rp(bill.tax)}`);
  lines.push(`*Total: ${rp(bill.total)}*`, bill.status === "paid" ? "Status: LUNAS" : "Status: belum dibayar", "", store.footer ?? "Terima kasih!");
  return lines.join("\n");
}

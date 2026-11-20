import { format, formatDistanceToNowStrict, parseISO } from "date-fns";
import { id } from "date-fns/locale";

const rupiah = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 });

/** Format Rupiah: Rp 1.234.567 */
export function rp(value: number | string | null | undefined): string {
  const n = Number(value ?? 0);
  const sign = n < 0 ? "-" : "";
  return `${sign}Rp ${rupiah.format(Math.abs(Math.round(n)))}`;
}

export function num(value: number | string | null | undefined, digits = 0): string {
  return new Intl.NumberFormat("id-ID", { maximumFractionDigits: digits }).format(Number(value ?? 0));
}

function toDate(v: string | Date) {
  return typeof v === "string" ? parseISO(v) : v;
}

export function fdate(v?: string | Date | null, pattern = "d MMM yyyy"): string {
  if (!v) return "-";
  return format(toDate(v), pattern, { locale: id });
}

export function fdatetime(v?: string | Date | null): string {
  return fdate(v, "d MMM yyyy, HH:mm");
}

export function ftime(v?: string | Date | null): string {
  return fdate(v, "HH:mm");
}

export function ago(v?: string | Date | null): string {
  if (!v) return "-";
  return formatDistanceToNowStrict(toDate(v), { locale: id, addSuffix: true });
}

export function minutesSince(v?: string | null): number {
  if (!v) return 0;
  return Math.max(0, Math.floor((Date.now() - toDate(v).getTime()) / 60000));
}

export function today(): string {
  return format(new Date(), "yyyy-MM-dd");
}

export function daysAgo(n: number): string {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return format(d, "yyyy-MM-dd");
}

/** Normalisasi nomor WA ke 62xxxx untuk link wa.me */
export function waNumber(phone?: string | null): string | null {
  if (!phone) return null;
  let p = phone.replace(/\D+/g, "");
  if (p.startsWith("0")) p = "62" + p.slice(1);
  else if (p.startsWith("8")) p = "62" + p;
  return p.length >= 9 ? p : null;
}

/** Parse input angka "1.500.000" -> 1500000 */
export function parseNumber(v: string): number {
  const n = Number(v.replace(/[^\d-]/g, ""));
  return Number.isFinite(n) ? n : 0;
}

export const LABELS: Record<string, { text: string; className: string }> = {
  best_seller: { text: "Best Seller", className: "bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300" },
  new: { text: "Baru", className: "bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300" },
  spicy: { text: "Pedas", className: "bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300" },
  vegan: { text: "Vegan", className: "bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300" },
  recommended: { text: "Rekomendasi", className: "bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300" },
};

export const ITEM_STATUS: Record<string, { text: string; className: string }> = {
  new: { text: "Baru", className: "bg-info-soft text-info" },
  processing: { text: "Diproses", className: "bg-warning-soft text-warning" },
  ready: { text: "Siap", className: "bg-success-soft text-success" },
  served: { text: "Disajikan", className: "bg-surface-3 text-muted" },
  cancelled: { text: "Dibatalkan", className: "bg-danger-soft text-danger line-through" },
};

export const BILL_STATUS: Record<string, { text: string; className: string }> = {
  open: { text: "Terbuka", className: "bg-info-soft text-info" },
  awaiting_payment: { text: "Menunggu Bayar", className: "bg-warning-soft text-warning" },
  paid: { text: "Lunas", className: "bg-success-soft text-success" },
  void: { text: "Void", className: "bg-danger-soft text-danger" },
  refunded: { text: "Refund", className: "bg-danger-soft text-danger" },
  merged: { text: "Digabung", className: "bg-surface-3 text-muted" },
};

export const TABLE_STATUS: Record<string, { text: string; className: string; dot: string }> = {
  empty: { text: "Kosong", className: "border-border bg-surface", dot: "bg-emerald-500" },
  occupied: { text: "Terisi", className: "border-info/40 bg-info-soft", dot: "bg-blue-500" },
  awaiting_payment: { text: "Menunggu Bayar", className: "border-warning/50 bg-warning-soft", dot: "bg-amber-500" },
  needs_cleaning: { text: "Perlu Dibersihkan", className: "border-danger/40 bg-danger-soft", dot: "bg-red-500" },
};

export const ORDER_TYPE: Record<string, string> = { dine_in: "Dine-in", takeaway: "Takeaway", delivery: "Delivery" };
export const PAY_METHOD: Record<string, string> = { cash: "Tunai", qris: "QRIS", transfer: "Transfer", card: "Kartu", gateway: "Online" };

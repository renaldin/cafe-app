"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

export type CartOption = { id: number; group: string; name: string; price_delta: number };
export type CartLine = {
  key: string;
  menu_id: number;
  name: string;
  image_url?: string | null;
  base_price: number;
  options: CartOption[];
  qty: number;
  note?: string;
};

type CartState = {
  /** keranjang per konteks (token meja / "takeaway") */
  carts: Record<string, CartLine[]>;
  /** kode pelacakan per konteks */
  tracking: Record<string, string>;
  customer: { name: string; phone: string };
  add: (ctx: string, line: Omit<CartLine, "key">) => void;
  setQty: (ctx: string, key: string, qty: number) => void;
  remove: (ctx: string, key: string) => void;
  clear: (ctx: string) => void;
  setTracking: (ctx: string, key: string) => void;
  setCustomer: (c: { name: string; phone: string }) => void;
};

export function lineKey(menuId: number, optionIds: number[], note?: string) {
  return `${menuId}:${[...optionIds].sort((a, b) => a - b).join(",")}:${note ?? ""}`;
}

export function linePrice(l: Pick<CartLine, "base_price" | "options">) {
  return l.base_price + l.options.reduce((s, o) => s + o.price_delta, 0);
}

export const useCart = create<CartState>()(
  persist(
    (set) => ({
      carts: {},
      tracking: {},
      customer: { name: "", phone: "" },
      add: (ctx, line) =>
        set((s) => {
          const list = [...(s.carts[ctx] ?? [])];
          const key = lineKey(line.menu_id, line.options.map((o) => o.id), line.note);
          const found = list.find((l) => l.key === key);
          if (found) found.qty = Math.min(found.qty + line.qty, 20);
          else list.push({ ...line, key });
          return { carts: { ...s.carts, [ctx]: list } };
        }),
      setQty: (ctx, key, qty) =>
        set((s) => ({
          carts: {
            ...s.carts,
            [ctx]: (s.carts[ctx] ?? []).map((l) => (l.key === key ? { ...l, qty } : l)).filter((l) => l.qty > 0),
          },
        })),
      remove: (ctx, key) => set((s) => ({ carts: { ...s.carts, [ctx]: (s.carts[ctx] ?? []).filter((l) => l.key !== key) } })),
      clear: (ctx) => set((s) => ({ carts: { ...s.carts, [ctx]: [] } })),
      setTracking: (ctx, key) => set((s) => ({ tracking: { ...s.tracking, [ctx]: key } })),
      setCustomer: (customer) => set({ customer }),
    }),
    { name: "cafe-cart", version: 1 },
  ),
);

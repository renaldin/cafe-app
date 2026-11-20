import type { Menu } from "@/types";

/** Cek jam tersedia menu (client-side, validasi akhir tetap di server) */
export function availableNow(menu: Pick<Menu, "available_from" | "available_until">, at = new Date()): boolean {
  if (!menu.available_from || !menu.available_until) return true;
  const t = at.toTimeString().slice(0, 5);
  const { available_from: f, available_until: u } = menu;
  return f <= u ? t >= f && t <= u : t >= f || t <= u;
}

export function orderable(menu: Menu): boolean {
  return menu.is_available && menu.is_active && availableNow(menu);
}

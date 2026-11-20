"use client";

import { Clock, Plus } from "lucide-react";
import { Badge } from "@/components/ui/card";
import { cn } from "@/lib/cn";
import { LABELS, rp } from "@/lib/format";
import { availableNow } from "@/lib/menu-utils";
import type { Menu } from "@/types";

export function MenuCard({ menu, onSelect, inCart }: { menu: Menu; onSelect: (m: Menu) => void; inCart: number }) {
  const timeOk = availableNow(menu);
  const disabled = !menu.is_available || !timeOk;
  return (
    <button
      onClick={() => !disabled && onSelect(menu)}
      disabled={disabled}
      className={cn(
        "group relative flex w-full gap-3 rounded-2xl border border-border bg-surface p-3 text-left transition hover:border-primary/40 active:scale-[0.99]",
        disabled && "opacity-60",
      )}
      aria-label={`${menu.name}, ${rp(menu.price)}${disabled ? ", tidak tersedia" : ""}`}
    >
      <div className="relative h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-surface-2">
        {menu.image_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={menu.image_url} alt="" loading="lazy" decoding="async" width={96} height={96} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-3xl" aria-hidden>
            ☕
          </div>
        )}
        {!menu.is_available && <span className="absolute inset-0 flex items-center justify-center bg-black/55 text-xs font-bold uppercase text-white">Habis</span>}
      </div>
      <div className="flex min-w-0 flex-1 flex-col">
        <div className="flex flex-wrap gap-1">
          {menu.labels?.slice(0, 2).map((l) => (
            <Badge key={l} className={LABELS[l]?.className}>
              {LABELS[l]?.text ?? l}
            </Badge>
          ))}
        </div>
        <p className="mt-0.5 line-clamp-1 font-semibold">{menu.name}</p>
        {menu.description && <p className="line-clamp-2 text-xs text-muted">{menu.description}</p>}
        {!timeOk && (
          <p className="mt-1 flex items-center gap-1 text-xs text-warning">
            <Clock className="h-3 w-3" /> Tersedia {menu.available_from}–{menu.available_until}
          </p>
        )}
        <div className="mt-auto flex items-center justify-between pt-1">
          <span className="font-bold text-primary">{rp(menu.price)}</span>
          {!disabled && (
            <span className="relative flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-fg shadow">
              <Plus className="h-4 w-4" />
              {inCart > 0 && <span className="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-accent px-1 text-center text-[11px] font-bold text-white">{inCart}</span>}
            </span>
          )}
        </div>
      </div>
    </button>
  );
}

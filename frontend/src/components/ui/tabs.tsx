"use client";

import { cn } from "@/lib/cn";

export function Tabs<T extends string>({ value, onChange, items, className }: { value: T; onChange: (v: T) => void; items: { value: T; label: React.ReactNode; count?: number }[]; className?: string }) {
  return (
    <div role="tablist" className={cn("no-scrollbar inline-flex max-w-full gap-1 overflow-x-auto rounded-xl bg-surface-2 p-1", className)}>
      {items.map((it) => (
        <button
          key={it.value}
          role="tab"
          aria-selected={value === it.value}
          onClick={() => onChange(it.value)}
          className={cn(
            "flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition",
            value === it.value ? "bg-surface text-fg shadow-sm" : "text-muted hover:text-fg",
          )}
        >
          {it.label}
          {it.count !== undefined && it.count > 0 && <span className="rounded-full bg-primary px-1.5 text-[11px] font-bold text-primary-fg">{it.count}</span>}
        </button>
      ))}
    </div>
  );
}

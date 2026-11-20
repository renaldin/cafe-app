"use client";

import { Minus, Plus } from "lucide-react";
import { cn } from "@/lib/cn";

export function QtyStepper({ value, onChange, min = 0, max = 20, size = "md" }: { value: number; onChange: (v: number) => void; min?: number; max?: number; size?: "sm" | "md" }) {
  const s = size === "sm" ? "h-8 w-8" : "h-10 w-10";
  return (
    <div className="inline-flex items-center rounded-xl border border-border bg-surface">
      <button type="button" className={cn(s, "flex items-center justify-center rounded-l-xl text-fg hover:bg-surface-2 disabled:opacity-40")} onClick={() => onChange(Math.max(min, value - 1))} disabled={value <= min} aria-label="Kurangi">
        <Minus className="h-4 w-4" />
      </button>
      <span className="min-w-8 text-center font-semibold tabular-nums" aria-live="polite">
        {value}
      </span>
      <button type="button" className={cn(s, "flex items-center justify-center rounded-r-xl text-fg hover:bg-surface-2 disabled:opacity-40")} onClick={() => onChange(Math.min(max, value + 1))} disabled={value >= max} aria-label="Tambah">
        <Plus className="h-4 w-4" />
      </button>
    </div>
  );
}

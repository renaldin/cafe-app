"use client";

import { Search, X } from "lucide-react";
import { Input } from "./input";

export function SearchInput({ value, onChange, placeholder = "Cari...", className }: { value: string; onChange: (v: string) => void; placeholder?: string; className?: string }) {
  return (
    <div className={`relative ${className ?? ""}`}>
      <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden />
      <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-9 pr-8" aria-label={placeholder} />
      {value && (
        <button onClick={() => onChange("")} className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted hover:text-fg" aria-label="Hapus pencarian">
          <X className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}

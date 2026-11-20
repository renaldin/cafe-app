"use client";

import { Monitor, Moon, Sun } from "lucide-react";
import { useTheme } from "next-themes";
import { useMounted } from "@/hooks/useMounted";
import { cn } from "@/lib/cn";

export function ThemeToggle({ className }: { className?: string }) {
  const { theme, setTheme } = useTheme();
  const mounted = useMounted();
  const order = ["system", "light", "dark"] as const;
  const current = mounted ? ((theme as (typeof order)[number]) ?? "system") : "system";
  const next = order[(order.indexOf(current) + 1) % order.length];
  const Icon = current === "dark" ? Moon : current === "light" ? Sun : Monitor;
  const label = { system: "Ikuti sistem", light: "Mode terang", dark: "Mode gelap" }[current];
  return (
    <button
      onClick={() => setTheme(next)}
      className={cn("inline-flex h-10 w-10 items-center justify-center rounded-xl text-muted transition hover:bg-surface-2 hover:text-fg", className)}
      title={`${label} (klik untuk ganti)`}
      aria-label={`Tema: ${label}. Klik untuk ganti`}
    >
      <Icon className="h-5 w-5" />
    </button>
  );
}

"use client";

import { Coffee, LogIn, ShoppingBag } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { ThemeToggle } from "@/components/ui/theme-toggle";
import { homeFor } from "@/components/layout/nav";
import { useCan } from "@/lib/permissions";
import { useAuth } from "@/stores/auth";

export default function Home() {
  const token = useAuth((s) => s.token);
  const hydrated = useAuth((s) => s.hydrated);
  const can = useCan();
  const router = useRouter();
  useEffect(() => {
    if (hydrated && token) router.replace(homeFor(can));
  }, [hydrated, token, router, can]);

  return (
    <main className="relative flex min-h-screen flex-col items-center justify-center px-6 text-center">
      <ThemeToggle className="absolute right-4 top-4" />
      <span className="mb-5 flex h-16 w-16 items-center justify-center rounded-3xl bg-primary text-primary-fg shadow-lg">
        <Coffee className="h-8 w-8" />
      </span>
      <h1 className="text-3xl font-bold tracking-tight">Selamat datang</h1>
      <p className="mt-2 max-w-sm text-muted">Pesan online untuk diambil atau diantar, atau masuk sebagai staf untuk mengelola cafe.</p>
      <div className="mt-8 flex w-full max-w-xs flex-col gap-3">
        <Link href="/pesan/?mode=takeaway" className="flex h-12 items-center justify-center gap-2 rounded-xl bg-primary font-semibold text-primary-fg hover:bg-primary-hover">
          <ShoppingBag className="h-5 w-5" /> Pesan Online
        </Link>
        <Link href="/login/" className="flex h-12 items-center justify-center gap-2 rounded-xl border border-border bg-surface font-semibold hover:bg-surface-2">
          <LogIn className="h-5 w-5" /> Masuk Staf
        </Link>
      </div>
      <p className="mt-10 text-xs text-muted">Makan di tempat? Scan QR yang ada di meja Anda.</p>
    </main>
  );
}

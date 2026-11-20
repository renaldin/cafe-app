"use client";

import { useQueryClient } from "@tanstack/react-query";
import { AnimatePresence, motion } from "framer-motion";
import { ChevronRight, Coffee, KeyRound, LogOut, Menu as MenuIcon, PanelLeftClose, PanelLeftOpen, X } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { ConfirmHost } from "@/components/ui/confirm";
import { ThemeToggle } from "@/components/ui/theme-toggle";
import { api } from "@/lib/api";
import { cn } from "@/lib/cn";
import { useCan } from "@/lib/permissions";
import { useAuth } from "@/stores/auth";
import { useUi } from "@/stores/ui";
import { Forbidden } from "./forbidden";
import { NAV, findPage } from "./nav";

function Sidebar({ collapsed, onNavigate }: { collapsed: boolean; onNavigate?: () => void }) {
  const pathname = usePathname();
  const can = useCan();
  return (
    <nav aria-label="Navigasi utama" className="scrollbar-thin flex-1 overflow-y-auto px-3 pb-6">
      {NAV.map((g) => {
        const items = g.items.filter((i) => can(i.perm));
        if (!items.length) return null;
        return (
          <div key={g.label} className="mt-4">
            {!collapsed && <p className="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-muted/80">{g.label}</p>}
            <ul className="space-y-0.5">
              {items.map((i) => {
                const active = pathname === i.href || (i.href !== "/" && pathname.startsWith(i.href));
                const Icon = i.icon;
                return (
                  <li key={i.href}>
                    <Link
                      href={i.href}
                      onClick={onNavigate}
                      title={collapsed ? i.label : undefined}
                      aria-current={active ? "page" : undefined}
                      className={cn(
                        "group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition",
                        active ? "bg-primary text-primary-fg shadow-sm" : "text-muted hover:bg-surface-2 hover:text-fg",
                        collapsed && "justify-center px-0",
                      )}
                    >
                      <Icon className="h-[18px] w-[18px] shrink-0" aria-hidden />
                      {!collapsed && <span className="truncate">{i.label}</span>}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        );
      })}
    </nav>
  );
}

function Brand({ collapsed }: { collapsed?: boolean }) {
  return (
    <Link href="/" className={cn("flex items-center gap-2.5 px-5 py-4", collapsed && "justify-center px-0")}>
      <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-primary-fg">
        <Coffee className="h-5 w-5" />
      </span>
      {!collapsed && (
        <span className="leading-tight">
          <span className="block text-[15px] font-bold">Cafe POS</span>
          <span className="block text-[11px] text-muted">Manajemen Cafe</span>
        </span>
      )}
    </Link>
  );
}

function UserMenu() {
  const user = useAuth((s) => s.user);
  const clear = useAuth((s) => s.clear);
  const router = useRouter();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const h = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && setOpen(false);
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, []);
  const logout = async () => {
    try {
      await api.post("/auth/logout");
    } catch {
      /* token mungkin sudah kedaluwarsa */
    }
    clear();
    qc.clear();
    toast.success("Anda telah keluar.");
    router.replace("/login/");
  };
  const initials = (user?.name ?? "?").split(" ").map((s) => s[0]).slice(0, 2).join("").toUpperCase();
  return (
    <div className="relative" ref={ref}>
      <button onClick={() => setOpen((v) => !v)} className="flex items-center gap-2 rounded-xl p-1 pr-2 hover:bg-surface-2" aria-haspopup="menu" aria-expanded={open}>
        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-soft text-xs font-bold text-primary">{initials}</span>
        <span className="hidden text-left leading-tight sm:block">
          <span className="block max-w-[140px] truncate text-sm font-semibold">{user?.name}</span>
          <span className="block text-[11px] text-muted">{user?.roles?.join(", ")}</span>
        </span>
      </button>
      {open && (
        <div role="menu" className="card-shadow absolute right-0 top-12 z-40 w-52 rounded-2xl border border-border bg-surface p-1.5">
          <Link href="/akun/" role="menuitem" onClick={() => setOpen(false)} className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-surface-2">
            <KeyRound className="h-4 w-4" /> Akun & password
          </Link>
          <button role="menuitem" onClick={logout} className="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-danger hover:bg-danger-soft">
            <LogOut className="h-4 w-4" /> Keluar
          </button>
        </div>
      )}
    </div>
  );
}

function Breadcrumb() {
  const pathname = usePathname();
  const page = findPage(pathname);
  if (!page) return null;
  return (
    <nav aria-label="Breadcrumb" className="hidden min-w-0 items-center gap-1.5 text-sm text-muted md:flex">
      {page.group && <span>{page.group}</span>}
      {page.parent && (
        <>
          <ChevronRight className="h-3.5 w-3.5" />
          <Link href={page.parent.href} className="hover:text-fg">
            {page.parent.label}
          </Link>
        </>
      )}
      <ChevronRight className="h-3.5 w-3.5" />
      <span className="truncate font-medium text-fg">{page.item.label}</span>
    </nav>
  );
}

export function AppShell({ children, bare }: { children: React.ReactNode; bare?: boolean }) {
  const collapsed = useUi((s) => s.collapsed);
  const toggle = useUi((s) => s.toggleCollapsed);
  const [mobileOpen, setMobileOpen] = useState(false);
  const pathname = usePathname();
  const can = useCan();
  const page = findPage(pathname);
  const allowed = !page || can(page.item.perm);

  return (
    <div className="flex min-h-screen">
      {!bare && (
        <aside className={cn("sticky top-0 hidden h-screen shrink-0 flex-col border-r border-border bg-surface transition-[width] lg:flex", collapsed ? "w-[76px]" : "w-64")}>
          <Brand collapsed={collapsed} />
          <Sidebar collapsed={collapsed} />
        </aside>
      )}

      <AnimatePresence>
        {mobileOpen && (
          <div className="fixed inset-0 z-50 lg:hidden">
            <motion.div className="absolute inset-0 bg-black/50" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={() => setMobileOpen(false)} />
            <motion.aside
              initial={{ x: -300 }}
              animate={{ x: 0 }}
              exit={{ x: -300 }}
              transition={{ type: "spring", damping: 30, stiffness: 300 }}
              className="absolute inset-y-0 left-0 flex w-72 flex-col bg-surface"
            >
              <div className="flex items-center justify-between pr-3">
                <Brand />
                <button onClick={() => setMobileOpen(false)} className="rounded-lg p-2 hover:bg-surface-2" aria-label="Tutup menu">
                  <X className="h-5 w-5" />
                </button>
              </div>
              <Sidebar collapsed={false} onNavigate={() => setMobileOpen(false)} />
            </motion.aside>
          </div>
        )}
      </AnimatePresence>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="no-print sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-border bg-bg/85 px-3 backdrop-blur sm:px-5">
          {!bare && (
            <>
              <button onClick={() => setMobileOpen(true)} className="rounded-xl p-2 hover:bg-surface-2 lg:hidden" aria-label="Buka menu">
                <MenuIcon className="h-5 w-5" />
              </button>
              <button onClick={toggle} className="hidden rounded-xl p-2 text-muted hover:bg-surface-2 hover:text-fg lg:block" aria-label={collapsed ? "Perluas sidebar" : "Lipat sidebar"}>
                {collapsed ? <PanelLeftOpen className="h-5 w-5" /> : <PanelLeftClose className="h-5 w-5" />}
              </button>
            </>
          )}
          <Breadcrumb />
          <div className="ml-auto flex items-center gap-1">
            <ThemeToggle />
            <UserMenu />
          </div>
        </header>
        <main className={cn("flex-1", bare ? "p-3 sm:p-4" : "p-4 sm:p-6")}>
          <motion.div key={pathname} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.18 }}>
            {allowed ? children : <Forbidden />}
          </motion.div>
        </main>
      </div>
      <ConfirmHost />
    </div>
  );
}

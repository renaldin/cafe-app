"use client";

import { usePathname } from "next/navigation";
import { AppShell } from "@/components/layout/app-shell";
import { AuthGuard } from "@/components/layout/auth-guard";

export default function StaffLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  // Layar dapur tampil penuh tanpa sidebar
  const bare = pathname.startsWith("/dapur");
  return (
    <AuthGuard>
      <AppShell bare={bare}>{children}</AppShell>
    </AuthGuard>
  );
}

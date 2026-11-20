"use client";

import { useQuery } from "@tanstack/react-query";
import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import { api } from "@/lib/api";
import { useAuth, type AuthUser, type ShiftInfo } from "@/stores/auth";
import { Spinner } from "@/components/ui/card";
import { useMounted } from "@/hooks/useMounted";

/** Pastikan pengguna login; segarkan profil & permission dari server. */
export function AuthGuard({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const hydrated = useAuth((s) => s.hydrated);
  const token = useAuth((s) => s.token);
  const setSession = useAuth((s) => s.setSession);
  const mounted = useMounted();

  useEffect(() => {
    if (hydrated && !token) router.replace(`/login/?next=${encodeURIComponent(pathname)}`);
  }, [hydrated, token, router, pathname]);

  useQuery({
    queryKey: ["me"],
    enabled: hydrated && !!token,
    staleTime: 60_000,
    refetchInterval: 5 * 60_000,
    queryFn: async () => {
      const { data } = await api.get<{ data: { user: AuthUser; permissions: string[]; shift: ShiftInfo } }>("/auth/me");
      setSession(data.data);
      return data.data;
    },
  });

  // mounted: hindari mismatch hydrate (HTML statis selalu merender spinner)
  if (!mounted || !hydrated || !token) {
    return (
      <div className="flex min-h-screen items-center justify-center text-primary">
        <Spinner className="h-8 w-8" />
      </div>
    );
  }
  return <>{children}</>;
}

"use client";

import { useQuery, type QueryKey } from "@tanstack/react-query";
import type { AxiosInstance } from "axios";
import { useRef } from "react";

/**
 * Polling hemat resource: kirim ?since=<versi>, server membalas {changed:false} bila tak ada perubahan.
 * Otomatis berhenti saat tab tidak aktif (refetchIntervalInBackground: false).
 */
export function usePolling<T>(
  client: AxiosInstance,
  key: QueryKey,
  url: string,
  opts: { interval?: number; params?: Record<string, unknown>; enabled?: boolean } = {},
) {
  const cache = useRef<{ sig: string; version?: string; data?: T }>({ sig: "" });
  const sig = JSON.stringify([url, opts.params ?? {}]);

  return useQuery<T>({
    queryKey: [...key, opts.params ?? {}],
    enabled: opts.enabled ?? true,
    queryFn: async () => {
      // parameter berubah -> mulai dari awal (ambil penuh)
      if (cache.current.sig !== sig) cache.current = { sig };
      const res = await client.get(url, { params: { ...(opts.params ?? {}), since: cache.current.version } });
      const body = res.data as { data: T & { changed?: boolean }; meta?: { version?: string } };
      if (body.data && (body.data as { changed?: boolean }).changed === false && cache.current.data !== undefined) {
        return cache.current.data;
      }
      cache.current = { sig, version: body.meta?.version, data: body.data };
      return body.data;
    },
    refetchInterval: opts.interval ?? 5000,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: true,
    staleTime: 0,
  });
}

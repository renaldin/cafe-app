"use client";

import { useMutation, useQuery, useQueryClient, type QueryKey } from "@tanstack/react-query";
import { toast } from "sonner";
import { api, errMsg, type Paginated } from "@/lib/api";

export function useGet<T>(key: QueryKey, url: string, params?: Record<string, unknown>, opts: { enabled?: boolean } = {}) {
  return useQuery<T>({
    queryKey: [...key, params ?? {}],
    enabled: opts.enabled ?? true,
    queryFn: async () => (await api.get<{ data: T }>(url, { params })).data.data,
  });
}

export function usePaged<T>(key: QueryKey, url: string, params?: Record<string, unknown>) {
  return useQuery<Paginated<T>>({
    queryKey: [...key, params ?? {}],
    queryFn: async () => (await api.get<Paginated<T>>(url, { params })).data,
    placeholderData: (prev) => prev,
  });
}

type Method = "post" | "put" | "patch" | "delete";

/** Mutasi dengan toast sukses/gagal + invalidasi query */
export function useApiMutation<V = unknown>(method: Method, url: string | ((v: V) => string), invalidate: QueryKey[] = [], onDone?: (data: unknown) => void) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (vars: V) => {
      const u = typeof url === "function" ? url(vars) : url;
      const res = method === "delete" ? await api.delete(u, { data: vars }) : await api[method](u, vars);
      return res.data as { data: unknown; message: string };
    },
    onSuccess: (r) => {
      if (r.message) toast.success(r.message);
      invalidate.forEach((k) => void qc.invalidateQueries({ queryKey: k }));
      onDone?.(r.data);
    },
    onError: (e) => toast.error(errMsg(e)),
  });
}

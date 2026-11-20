"use client";

import axios, { AxiosError } from "axios";
import { useAuth } from "@/stores/auth";

export const API_URL = (process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1").replace(/\/$/, "");
export const APP_URL = (process.env.NEXT_PUBLIC_APP_URL || (typeof window !== "undefined" ? window.location.origin : "")).replace(/\/$/, "");

export type ApiResponse<T> = { data: T; message: string | null; meta: Record<string, unknown> & { version?: string } };
export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number; [k: string]: unknown };
};

/** API staf: otomatis menyertakan Bearer token & logout saat 401 */
export const api = axios.create({ baseURL: API_URL, timeout: 25000, headers: { Accept: "application/json" } });

api.interceptors.request.use((config) => {
  const token = useAuth.getState().token;
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (r) => r,
  (error: AxiosError) => {
    if (error.response?.status === 401 && useAuth.getState().token) {
      useAuth.getState().clear();
      if (typeof window !== "undefined" && !window.location.pathname.startsWith("/login")) {
        const next = encodeURIComponent(window.location.pathname + window.location.search);
        // di luar komponen React (interceptor), navigasi penuh disengaja untuk mereset state
        // eslint-disable-next-line @next/next/no-location-assign-relative-destination
        window.location.href = `/login/?expired=1&next=${next}`;
      }
    }
    return Promise.reject(error);
  },
);

/** API publik pelanggan (tanpa token) */
export const publicApi = axios.create({ baseURL: `${API_URL}/public`, timeout: 20000, headers: { Accept: "application/json" } });

export function errMsg(e: unknown, fallback = "Terjadi kesalahan. Silakan coba lagi."): string {
  const err = e as AxiosError<{ message?: string; errors?: Record<string, string[]> }>;
  if (err?.code === "ECONNABORTED") return "Koneksi lambat, permintaan kehabisan waktu.";
  if (err?.message === "Network Error") return "Tidak dapat terhubung ke server. Periksa koneksi internet.";
  return err?.response?.data?.message || fallback;
}

export function fieldErrors(e: unknown): Record<string, string> {
  const err = e as AxiosError<{ errors?: Record<string, string[]> }>;
  const out: Record<string, string> = {};
  Object.entries(err?.response?.data?.errors ?? {}).forEach(([k, v]) => (out[k] = Array.isArray(v) ? v[0] : String(v)));
  return out;
}

/** Unduh file (Excel/PDF) dari endpoint terproteksi */
export async function download(url: string, params: Record<string, unknown>, filename: string) {
  const res = await api.get(url, { params, responseType: "blob" });
  const href = URL.createObjectURL(res.data as Blob);
  const a = document.createElement("a");
  a.href = href;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(href), 2000);
}

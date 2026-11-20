"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

export type AuthUser = { id: number; name: string; username: string; email?: string | null; roles: string[] };
export type ShiftInfo = { id: number; opened_at: string; opening_cash: number } | null;

type AuthState = {
  token: string | null;
  user: AuthUser | null;
  permissions: string[];
  shift: ShiftInfo;
  hydrated: boolean;
  setSession: (p: { token?: string; user: AuthUser; permissions: string[]; shift: ShiftInfo }) => void;
  setShift: (s: ShiftInfo) => void;
  clear: () => void;
};

export const useAuth = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      permissions: [],
      shift: null,
      hydrated: false,
      setSession: ({ token, user, permissions, shift }) =>
        set((s) => ({ token: token ?? s.token, user, permissions, shift })),
      setShift: (shift) => set({ shift }),
      clear: () => set({ token: null, user: null, permissions: [], shift: null }),
    }),
    {
      name: "cafe-auth",
      partialize: (s) => ({ token: s.token, user: s.user, permissions: s.permissions, shift: s.shift }),
      onRehydrateStorage: () => () => {
        useAuth.setState({ hydrated: true });
      },
    },
  ),
);

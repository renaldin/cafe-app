"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

type UiState = {
  collapsed: boolean;
  soundOn: boolean;
  toggleCollapsed: () => void;
  setSound: (v: boolean) => void;
};

export const useUi = create<UiState>()(
  persist(
    (set) => ({
      collapsed: false,
      soundOn: true,
      toggleCollapsed: () => set((s) => ({ collapsed: !s.collapsed })),
      setSound: (soundOn) => set({ soundOn }),
    }),
    { name: "cafe-ui" },
  ),
);

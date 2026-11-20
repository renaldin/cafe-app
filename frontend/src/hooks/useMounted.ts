"use client";

import { useSyncExternalStore } from "react";

const subscribe = () => () => {};

/** true hanya di browser setelah hydrate (aman untuk localStorage) */
export function useMounted() {
  return useSyncExternalStore(subscribe, () => true, () => false);
}

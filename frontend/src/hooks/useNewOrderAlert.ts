"use client";

import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { flashTitle, playNewOrder, vibrate } from "@/lib/sound";
import { useUi } from "@/stores/ui";

/** Bunyikan notifikasi saat id pesanan terbaru bertambah */
export function useNewOrderAlert(latestId: number | undefined, message = "Pesanan baru masuk!") {
  const last = useRef<number | null>(null);
  const soundOn = useUi((s) => s.soundOn);
  useEffect(() => {
    if (latestId === undefined) return;
    if (last.current !== null && latestId > last.current) {
      if (soundOn) playNewOrder();
      vibrate();
      flashTitle(message);
      toast.info(message, { duration: 4000 });
    }
    last.current = latestId;
  }, [latestId, soundOn, message]);
}

/** Re-render berkala untuk timer */
export function useTick(ms = 15000) {
  const [, set] = useState(0);
  useEffect(() => {
    const t = setInterval(() => set((n) => n + 1), ms);
    return () => clearInterval(t);
  }, [ms]);
}

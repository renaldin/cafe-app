"use client";

import { Volume2, VolumeX } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { playNewOrder, unlockAudio } from "@/lib/sound";
import { useUi } from "@/stores/ui";

export function SoundToggle() {
  const soundOn = useUi((s) => s.soundOn);
  const setSound = useUi((s) => s.setSound);
  return (
    <Button
      variant="outline"
      size="sm"
      onClick={() => {
        unlockAudio();
        const v = !soundOn;
        setSound(v);
        if (v) {
          playNewOrder();
          toast.success("Notifikasi suara aktif");
        }
      }}
      aria-pressed={soundOn}
      title="Notifikasi suara pesanan baru"
    >
      {soundOn ? <Volume2 className="h-4 w-4" /> : <VolumeX className="h-4 w-4" />}
      <span className="hidden sm:inline">{soundOn ? "Suara aktif" : "Suara mati"}</span>
    </Button>
  );
}

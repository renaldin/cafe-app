"use client";

import { AlertTriangle } from "lucide-react";
import { useState } from "react";
import { create } from "zustand";
import { Button } from "./button";
import { Textarea } from "./input";
import { Modal } from "./modal";

type ConfirmOptions = {
  title: string;
  description?: React.ReactNode;
  confirmText?: string;
  danger?: boolean;
  /** minta alasan (wajib) */
  reason?: boolean | string;
};

type ConfirmState = {
  opts: ConfirmOptions | null;
  resolve: ((v: { ok: boolean; reason?: string }) => void) | null;
  ask: (o: ConfirmOptions) => Promise<{ ok: boolean; reason?: string }>;
  close: (v: { ok: boolean; reason?: string }) => void;
};

const useConfirmStore = create<ConfirmState>((set, get) => ({
  opts: null,
  resolve: null,
  ask: (opts) => new Promise((resolve) => set({ opts, resolve })),
  close: (v) => {
    get().resolve?.(v);
    set({ opts: null, resolve: null });
  },
}));

/** const ok = await confirm({ title: 'Hapus?' }) */
export function confirm(o: ConfirmOptions) {
  return useConfirmStore.getState().ask(o);
}

export function ConfirmHost() {
  const { opts, close } = useConfirmStore();
  const [reason, setReason] = useState("");
  const needReason = !!opts?.reason;
  const done = (ok: boolean) => {
    close({ ok, reason: ok ? reason.trim() : undefined });
    setReason("");
  };
  return (
    <Modal
      open={!!opts}
      onClose={() => done(false)}
      size="sm"
      title={
        <span className="flex items-center gap-2">
          {opts?.danger && <AlertTriangle className="h-5 w-5 text-danger" />}
          {opts?.title}
        </span>
      }
      footer={
        <>
          <Button variant="outline" onClick={() => done(false)}>
            Batal
          </Button>
          <Button variant={opts?.danger ? "danger" : "primary"} disabled={needReason && reason.trim().length < 3} onClick={() => done(true)}>
            {opts?.confirmText ?? "Ya, lanjutkan"}
          </Button>
        </>
      }
    >
      {opts?.description && <div className="text-sm text-muted">{opts.description}</div>}
      {needReason && (
        <div className="mt-3">
          <label className="mb-1.5 block text-sm font-medium" htmlFor="confirm-reason">
            {typeof opts?.reason === "string" ? opts.reason : "Alasan"} <span className="text-danger">*</span>
          </label>
          <Textarea id="confirm-reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Tulis alasan (min. 3 karakter)" />
        </div>
      )}
    </Modal>
  );
}

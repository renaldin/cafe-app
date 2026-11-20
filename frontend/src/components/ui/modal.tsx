"use client";

import { AnimatePresence, motion } from "framer-motion";
import { X } from "lucide-react";
import { useEffect, useId, useRef } from "react";
import { createPortal } from "react-dom";
import { cn } from "@/lib/cn";
import { useMounted } from "@/hooks/useMounted";

type ModalProps = {
  open: boolean;
  onClose: () => void;
  title?: React.ReactNode;
  description?: React.ReactNode;
  children: React.ReactNode;
  footer?: React.ReactNode;
  size?: "sm" | "md" | "lg" | "xl";
  /** tampil sebagai bottom sheet di layar kecil */
  sheet?: boolean;
};

const sizes = { sm: "sm:max-w-sm", md: "sm:max-w-lg", lg: "sm:max-w-2xl", xl: "sm:max-w-4xl" };

export function Modal({ open, onClose, title, description, children, footer, size = "md", sheet = true }: ModalProps) {
  const mounted = useMounted();
  const titleId = useId();
  const panel = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    document.addEventListener("keydown", onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    setTimeout(() => panel.current?.querySelector<HTMLElement>("input,select,textarea,button:not([data-close])")?.focus(), 50);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = prev;
    };
  }, [open, onClose]);

  if (!mounted) return null;
  return createPortal(
    <AnimatePresence>
      {open && (
        <div className={cn("fixed inset-0 z-50 flex justify-center", sheet ? "items-end sm:items-center" : "items-center p-4")}>
          <motion.div
            className="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
          />
          <motion.div
            ref={panel}
            role="dialog"
            aria-modal="true"
            aria-labelledby={title ? titleId : undefined}
            initial={{ opacity: 0, y: 40 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 40 }}
            transition={{ type: "spring", damping: 30, stiffness: 380 }}
            className={cn(
              "relative flex max-h-[92vh] w-full flex-col bg-surface text-fg shadow-2xl",
              sheet ? "rounded-t-3xl sm:m-4 sm:rounded-3xl" : "rounded-3xl",
              sizes[size],
            )}
          >
            {sheet && <div className="mx-auto mt-2 h-1.5 w-10 rounded-full bg-surface-3 sm:hidden" aria-hidden />}
            {(title || description) && (
              <div className="flex items-start justify-between gap-4 px-5 pb-3 pt-4">
                <div>
                  {title && (
                    <h2 id={titleId} className="text-lg font-bold">
                      {title}
                    </h2>
                  )}
                  {description && <p className="mt-0.5 text-sm text-muted">{description}</p>}
                </div>
                <button data-close onClick={onClose} className="rounded-lg p-1.5 text-muted hover:bg-surface-2" aria-label="Tutup">
                  <X className="h-5 w-5" />
                </button>
              </div>
            )}
            <div className="scrollbar-thin flex-1 overflow-y-auto px-5 pb-5">{children}</div>
            {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-border px-5 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">{footer}</div>}
          </motion.div>
        </div>
      )}
    </AnimatePresence>,
    document.body,
  );
}

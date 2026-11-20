"use client";

import { useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { QtyStepper } from "@/components/ui/qty";
import { cn } from "@/lib/cn";
import { LABELS, rp } from "@/lib/format";
import type { CartOption } from "@/stores/cart";
import type { Menu, ModifierGroup } from "@/types";

function initialSelection(groups: ModifierGroup[]): Record<number, number[]> {
  const sel: Record<number, number[]> = {};
  groups.forEach((g) => {
    const def = g.options.find((o) => o.is_default && o.is_available) ?? (g.is_required && g.selection === "single" ? g.options.find((o) => o.is_available) : undefined);
    sel[g.id] = def ? [def.id] : [];
  });
  return sel;
}

/** Bottom sheet pemilihan opsi menu (dipakai pelanggan & pesanan manual staf) */
export function ItemSheet({
  menu,
  onClose,
  onAdd,
  maxQty = 20,
}: {
  menu: Menu | null;
  onClose: () => void;
  onAdd: (p: { menu: Menu; options: CartOption[]; qty: number; note: string }) => void;
  maxQty?: number;
}) {
  return (
    <Modal open={!!menu} onClose={onClose} size="md" title={menu?.name} description={menu ? rp(menu.price) : undefined}>
      {menu && <ItemSheetBody key={menu.id} menu={menu} onAdd={onAdd} maxQty={maxQty} />}
    </Modal>
  );
}

function ItemSheetBody({ menu, onAdd, maxQty }: { menu: Menu; onAdd: (p: { menu: Menu; options: CartOption[]; qty: number; note: string }) => void; maxQty: number }) {
  const groups = useMemo(() => menu.modifier_groups ?? [], [menu]);
  const [sel, setSel] = useState<Record<number, number[]>>(() => initialSelection(groups));
  const [qty, setQty] = useState(1);
  const [note, setNote] = useState("");

  const options: CartOption[] = groups.flatMap((g) =>
    g.options.filter((o) => sel[g.id]?.includes(o.id)).map((o) => ({ id: o.id, group: g.name, name: o.name, price_delta: o.price_delta })),
  );
  const unit = menu.price + options.reduce((s, o) => s + o.price_delta, 0);
  const missing = groups.filter((g) => {
    const n = sel[g.id]?.length ?? 0;
    const min = g.is_required ? Math.max(1, g.min_select) : g.min_select;
    return n < min;
  });

  const toggle = (g: ModifierGroup, id: number) =>
    setSel((s) => {
      const cur = s[g.id] ?? [];
      if (g.selection === "single") return { ...s, [g.id]: g.is_required ? [id] : cur.includes(id) ? [] : [id] };
      if (cur.includes(id)) return { ...s, [g.id]: cur.filter((x) => x !== id) };
      const max = g.max_select || g.options.length;
      if (cur.length >= max) return s;
      return { ...s, [g.id]: [...cur, id] };
    });

  return (
    <div className="space-y-5">
      {menu.image_url && (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={menu.image_url} alt={menu.name} className="aspect-[16/9] w-full rounded-2xl object-cover" loading="lazy" />
      )}
      {(menu.description || menu.labels.length > 0) && (
        <div>
          <div className="mb-1 flex flex-wrap gap-1">
            {menu.labels.map((l) => (
              <span key={l} className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", LABELS[l]?.className)}>
                {LABELS[l]?.text ?? l}
              </span>
            ))}
          </div>
          {menu.description && <p className="text-sm text-muted">{menu.description}</p>}
        </div>
      )}

      {groups.map((g) => (
        <fieldset key={g.id}>
          <legend className="mb-2 flex w-full items-center justify-between">
            <span className="font-semibold">{g.name}</span>
            <span className={cn("rounded-full px-2 py-0.5 text-[11px] font-semibold", g.is_required ? "bg-primary-soft text-primary" : "bg-surface-2 text-muted")}>
              {g.is_required ? "Wajib" : "Opsional"}
              {g.selection === "multiple" && ` · maks ${g.max_select || g.options.length}`}
            </span>
          </legend>
          <div className="space-y-1.5">
            {g.options.map((o) => {
              const active = sel[g.id]?.includes(o.id);
              return (
                <label
                  key={o.id}
                  className={cn(
                    "flex cursor-pointer items-center justify-between rounded-xl border px-3 py-2.5 text-sm transition",
                    active ? "border-primary bg-primary-soft" : "border-border hover:bg-surface-2",
                    !o.is_available && "pointer-events-none opacity-50",
                  )}
                >
                  <span className="flex items-center gap-2.5">
                    <input
                      type={g.selection === "single" ? "radio" : "checkbox"}
                      name={`g-${g.id}`}
                      checked={!!active}
                      onChange={() => toggle(g, o.id)}
                      disabled={!o.is_available}
                      className="h-4 w-4 accent-[var(--primary)]"
                    />
                    {o.name}
                    {!o.is_available && <span className="text-xs text-danger">(habis)</span>}
                  </span>
                  {o.price_delta !== 0 && <span className="font-medium text-muted">{o.price_delta > 0 ? "+" : ""}{rp(o.price_delta)}</span>}
                </label>
              );
            })}
          </div>
        </fieldset>
      ))}

      <div>
        <label htmlFor="item-note" className="mb-1.5 block font-semibold">
          Catatan <span className="font-normal text-muted">(opsional)</span>
        </label>
        <Textarea id="item-note" maxLength={200} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Contoh: tanpa bawang, es dipisah" className="min-h-[60px]" />
      </div>

      <div className="sticky bottom-0 -mx-5 flex items-center gap-3 border-t border-border bg-surface px-5 pt-3">
        <QtyStepper value={qty} onChange={setQty} min={1} max={maxQty} />
        <Button
          size="lg"
          className="flex-1"
          disabled={missing.length > 0}
          onClick={() => onAdd({ menu, options, qty, note: note.trim() })}
        >
          {missing.length > 0 ? `Pilih ${missing[0].name}` : `Tambah · ${rp(unit * qty)}`}
        </Button>
      </div>
    </div>
  );
}

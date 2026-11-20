"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { Modal } from "@/components/ui/modal";
import { SearchInput } from "@/components/ui/search-input";
import { Switch } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/card";
import { api, errMsg } from "@/lib/api";
import type { Menu } from "@/types";

/** Toggle cepat menu habis/tersedia dari layar dapur/kasir */
export function SoldOutModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const q = useQuery({
    queryKey: ["menus-all-lite"],
    enabled: open,
    queryFn: async () => (await api.get<{ data: Menu[] }>("/menus", { params: { all: 1 } })).data.data,
  });
  const toggle = useMutation({
    mutationFn: async ({ id, v }: { id: number; v: boolean }) => api.patch(`/menus/${id}/availability`, { is_available: v }),
    onSuccess: (r) => {
      toast.success(r.data.message);
      void qc.invalidateQueries({ queryKey: ["menus-all-lite"] });
    },
    onError: (e) => toast.error(errMsg(e)),
  });
  const list = (q.data ?? []).filter((m) => m.name.toLowerCase().includes(search.toLowerCase()));
  return (
    <Modal open={open} onClose={onClose} title="Ketersediaan menu" description="Matikan menu yang habis agar tidak bisa dipesan pelanggan.">
      <SearchInput value={search} onChange={setSearch} placeholder="Cari menu..." className="mb-3" />
      {q.isLoading && <Skeleton className="h-40" />}
      <ul className="divide-y divide-border">
        {list.map((m) => (
          <li key={m.id} className="flex items-center justify-between py-2.5">
            <div>
              <p className="font-medium">{m.name}</p>
              <p className="text-xs text-muted">{m.category?.name}</p>
            </div>
            <Switch checked={m.is_available} onChange={(v) => toggle.mutate({ id: m.id, v })} label={<span className={m.is_available ? "text-success" : "text-danger"}>{m.is_available ? "Tersedia" : "Habis"}</span>} />
          </li>
        ))}
      </ul>
    </Modal>
  );
}

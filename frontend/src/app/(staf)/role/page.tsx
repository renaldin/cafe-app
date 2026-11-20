"use client";

import { Lock, Plus, Save, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Badge, Card, CardHeader, Skeleton } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { Checkbox, Input } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet } from "@/hooks/useApi";
import { cn } from "@/lib/cn";

type Role = { id: number; name: string; users_count: number; is_system: boolean; permissions: string[] };

export default function RolePage() {
  const roles = useGet<Role[]>(["roles"], "/roles");
  const perms = useGet<Record<string, Record<string, string>>>(["permissions"], "/permissions");
  const [pick, setSelId] = useState<number | "new" | null>(null);
  const selId = pick ?? roles.data?.[0]?.id ?? null;
  const sel = selId === "new" ? null : roles.data?.find((r) => r.id === selId) ?? null;
  // draft per role terpilih; bila belum diedit, ambil dari data server
  const [draft, setDraft] = useState<{ key: string; name: string; checked: string[] } | null>(null);
  const draftKey = String(selId);
  const current = draft && draft.key === draftKey ? draft : { key: draftKey, name: sel?.name ?? "", checked: sel?.permissions ?? [] };
  const { name, checked } = current;
  const setName = (v: string) => setDraft({ ...current, name: v });
  const setChecked = (fn: (c: string[]) => string[]) => setDraft({ ...current, checked: fn(current.checked) });
  const save = useApiMutation<{ name: string; permissions: string[] }>(selId === "new" ? "post" : "put", selId === "new" ? "/roles" : `/roles/${selId}`, [["roles"], ["roles-list"]], (d) => { setDraft(null); setSelId((d as { id: number }).id); });
  const del = useApiMutation<{ id: number }>("delete", (v) => `/roles/${v.id}`, [["roles"], ["roles-list"]], () => setSelId(null));

  const toggleGroup = (keys: string[], on: boolean) => setChecked((c) => (on ? [...new Set([...c, ...keys])] : c.filter((k) => !keys.includes(k))));
  const locked = !!sel?.is_system;

  return (
    <div>
      <PageHeader title="Role & Akses" description="Atur hak akses per modul. Otorisasi juga ditegakkan di server." actions={<Button onClick={() => setSelId("new")}><Plus className="h-4 w-4" /> Role baru</Button>} />
      <div className="grid gap-4 lg:grid-cols-[260px_1fr]">
        <Card className="h-fit p-2">
          {roles.isLoading && <Skeleton className="h-40" />}
          {roles.data?.map((r) => (
            <button key={r.id} onClick={() => setSelId(r.id)} className={cn("flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm", selId === r.id ? "bg-primary text-primary-fg" : "hover:bg-surface-2")}>
              <span className="flex items-center gap-1.5 font-medium">{r.is_system && <Lock className="h-3.5 w-3.5" />}{r.name}</span>
              <span className="text-xs opacity-75">{r.users_count} staf</span>
            </button>
          ))}
        </Card>
        <Card>
          <CardHeader
            title={locked ? `${sel?.name} (akses penuh, tidak dapat diubah)` : <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Nama role" className="max-w-xs" aria-label="Nama role" />}
            action={!locked && (
              <div className="flex gap-2">
                {sel && <Button variant="outline" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus role ${sel.name}?`, danger: true }); if (r.ok) del.mutate({ id: sel.id }); }}><Trash2 className="h-4 w-4" /></Button>}
                <Button loading={save.isPending} disabled={!name} onClick={() => save.mutate({ name, permissions: checked })}><Save className="h-4 w-4" /> Simpan</Button>
              </div>
            )}
          />
          <div className="grid gap-4 p-5 md:grid-cols-2">
            {Object.entries(perms.data ?? {}).map(([group, items]) => {
              const keys = Object.keys(items);
              const all = keys.every((k) => checked.includes(k));
              return (
                <fieldset key={group} disabled={locked} className="rounded-2xl border border-border p-4">
                  <legend className="flex w-full items-center justify-between px-1">
                    <span className="font-semibold">{group}</span>
                  </legend>
                  <div className="mb-2"><Checkbox checked={all} onChange={(v) => toggleGroup(keys, v)} label={<span className="text-xs text-muted">Pilih semua</span>} /></div>
                  <div className="space-y-1.5">
                    {Object.entries(items).map(([k, label]) => (
                      <Checkbox key={k} checked={checked.includes(k)} onChange={(v) => setChecked((c) => (v ? [...c, k] : c.filter((x) => x !== k)))} label={<span>{label} <Badge className="bg-surface-2 font-mono text-[10px] text-muted">{k}</Badge></span>} />
                    ))}
                  </div>
                </fieldset>
              );
            })}
          </div>
        </Card>
      </div>
    </div>
  );
}

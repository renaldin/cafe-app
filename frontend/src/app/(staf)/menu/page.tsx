"use client";

import { Download, Pencil, Plus, Trash2, Upload } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Badge, Card } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Select, Switch } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { useApiMutation, useGet, usePaged } from "@/hooks/useApi";
import { useDebounce } from "@/hooks/useDebounce";
import { api, download, errMsg } from "@/lib/api";
import { LABELS, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Category, Menu } from "@/types";

export default function MenuPage() {
  const can = useCan();
  const router = useRouter();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [cat, setCat] = useState("");
  const s = useDebounce(search);
  const list = usePaged<Menu>(["menus"], "/menus", { page, search: s || undefined, category_id: cat || undefined });
  const cats = useGet<Category[]>(["categories"], "/categories");
  const toggle = useApiMutation<{ id: number; is_available: boolean }>("patch", (v) => `/menus/${v.id}/availability`, [["menus"]]);
  const del = useApiMutation<{ id: number }>("delete", (v) => `/menus/${v.id}`, [["menus"]]);
  const fileRef = useRef<HTMLInputElement>(null);
  const [importing, setImporting] = useState(false);

  const onImport = async (f: File) => {
    setImporting(true);
    try {
      const fd = new FormData();
      fd.append("file", f);
      const { data } = await api.post("/menus/import", fd);
      toast.success(data.message);
      (data.data.errors as string[]).slice(0, 5).forEach((e) => toast.warning(e));
      void list.refetch();
    } catch (e) {
      toast.error(errMsg(e));
    } finally {
      setImporting(false);
      if (fileRef.current) fileRef.current.value = "";
    }
  };

  const columns: Column<Menu>[] = [
    {
      header: "Menu",
      cell: (m) => (
        <div className="flex items-center gap-3">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          {m.image_url ? <img src={m.image_url} alt="" className="h-10 w-10 rounded-lg object-cover" loading="lazy" /> : <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-2">☕</div>}
          <div>
            <p className="font-semibold">{m.name}{m.is_bundle && <Badge className="ml-1 bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Paket</Badge>}</p>
            <div className="flex gap-1">{m.labels.map((l) => <Badge key={l} className={LABELS[l]?.className}>{LABELS[l]?.text}</Badge>)}</div>
          </div>
        </div>
      ),
    },
    { header: "Kategori", cell: (m) => m.category?.name },
    { header: "Harga", cell: (m) => rp(m.price) },
    { header: "HPP", cell: (m) => (m.cost !== undefined ? <span className="text-muted">{rp(m.cost)} <span className="text-xs">({m.price ? Math.round(((m.price - (m.cost ?? 0)) / m.price) * 100) : 0}% margin)</span></span> : "-") },
    { header: "Tersedia", cell: (m) => <Switch checked={m.is_available} disabled={!can("menu.toggle")} onChange={(v) => toggle.mutate({ id: m.id, is_available: v })} /> },
    {
      header: "",
      className: "text-right",
      cell: (m) =>
        can("menu.manage") && (
          <div className="flex justify-end gap-1">
            <Button size="sm" variant="ghost" onClick={() => router.push(`/menu/form/?id=${m.id}`)} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button>
            <Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus ${m.name}?`, danger: true }); if (r.ok) del.mutate({ id: m.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button>
          </div>
        ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Menu"
        description="Kelola menu, harga, foto, opsi, dan resep."
        actions={
          <>
            {can("menu.import_export") && (
              <>
                <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv" hidden onChange={(e) => e.target.files?.[0] && onImport(e.target.files[0])} />
                <Button variant="outline" size="sm" loading={importing} onClick={() => fileRef.current?.click()}><Upload className="h-4 w-4" /> Import</Button>
                <Button variant="outline" size="sm" onClick={() => download("/menus/export", {}, "menu.xlsx").catch((e) => toast.error(errMsg(e)))}><Download className="h-4 w-4" /> Export</Button>
              </>
            )}
            {can("menu.manage") && <Link href="/menu/form/"><Button size="sm"><Plus className="h-4 w-4" /> Menu</Button></Link>}
          </>
        }
      />
      <Card>
        <div className="flex flex-col gap-2 border-b border-border p-4 sm:flex-row">
          <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Cari nama / SKU..." className="flex-1" />
          <Select value={cat} onChange={(e) => { setCat(e.target.value); setPage(1); }} className="sm:w-52" aria-label="Filter kategori">
            <option value="">Semua kategori</option>
            {cats.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} />
      </Card>
    </div>
  );
}

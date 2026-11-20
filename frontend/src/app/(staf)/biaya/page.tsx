"use client";

import { Pencil, Plus, Settings2, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, MoneyInput, Select } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, useGet, usePaged } from "@/hooks/useApi";
import { daysAgo, fdate, rp, today } from "@/lib/format";

type Expense = { id: number; expense_category_id: number; date: string; amount: number; note: string | null; category: { name: string } | null; user: { name: string } | null };

export default function BiayaPage() {
  const [page, setPage] = useState(1);
  const [from, setFrom] = useState(daysAgo(29));
  const [to, setTo] = useState(today());
  const list = usePaged<Expense>(["expenses"], "/expenses", { page, from, to });
  const cats = useGet<{ id: number; name: string }[]>(["expense-categories"], "/expense-categories");
  const [edit, setEdit] = useState<Partial<Expense> | null>(null);
  const [catModal, setCatModal] = useState(false);
  const [catName, setCatName] = useState("");
  const save = useApiMutation<Partial<Expense>>(edit?.id ? "put" : "post", (v) => (v.id ? `/expenses/${v.id}` : "/expenses"), [["expenses"]], () => setEdit(null));
  const del = useApiMutation<{ id: number }>("delete", (v) => `/expenses/${v.id}`, [["expenses"]]);
  const addCat = useApiMutation<{ name: string }>("post", "/expense-categories", [["expense-categories"]], () => setCatName(""));
  const delCat = useApiMutation<{ id: number }>("delete", (v) => `/expense-categories/${v.id}`, [["expense-categories"]]);
  const columns: Column<Expense>[] = [
    { header: "Tanggal", cell: (e) => fdate(e.date) },
    { header: "Kategori", cell: (e) => <b>{e.category?.name}</b> },
    { header: "Keterangan", cell: (e) => e.note ?? "-" },
    { header: "Nominal", cell: (e) => rp(e.amount) },
    { header: "Dicatat oleh", cell: (e) => e.user?.name },
    { header: "", className: "text-right", cell: (e) => <div className="flex justify-end gap-1"><Button size="sm" variant="ghost" onClick={() => setEdit({ ...e, date: e.date.slice(0, 10) })} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button><Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: "Hapus biaya ini?", danger: true }); if (r.ok) del.mutate({ id: e.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button></div> },
  ];
  return (
    <div>
      <PageHeader title="Biaya Operasional" description="Dipakai di laporan laba rugi (sewa, listrik, gaji, dll)." actions={<><Button variant="outline" onClick={() => setCatModal(true)}><Settings2 className="h-4 w-4" /> Kategori</Button><Button onClick={() => setEdit({ date: today(), expense_category_id: cats.data?.[0]?.id, amount: 0 })}><Plus className="h-4 w-4" /> Biaya</Button></>} />
      <Card>
        <div className="flex flex-wrap items-end gap-2 border-b border-border p-4">
          <Field label="Dari"><Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></Field>
          <Field label="Sampai"><Input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
          <p className="ml-auto text-sm">Total periode: <b className="text-lg">{rp(Number(list.data?.meta?.sum ?? 0))}</b></p>
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} />
      </Card>
      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit?.id ? "Ubah biaya" : "Catat biaya"} size="sm" footer={<Button loading={save.isPending} onClick={() => edit && save.mutate(edit)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-3">
            <Field label="Tanggal"><Input type="date" value={edit.date ?? ""} onChange={(e) => setEdit({ ...edit, date: e.target.value })} /></Field>
            <Field label="Kategori"><Select value={edit.expense_category_id} onChange={(e) => setEdit({ ...edit, expense_category_id: Number(e.target.value) })}>{cats.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
            <Field label="Nominal"><MoneyInput value={edit.amount ?? 0} onChange={(v) => setEdit({ ...edit, amount: v })} /></Field>
            <Field label="Keterangan"><Input value={edit.note ?? ""} onChange={(e) => setEdit({ ...edit, note: e.target.value })} /></Field>
          </div>
        )}
      </Modal>
      <Modal open={catModal} onClose={() => setCatModal(false)} title="Kategori biaya" size="sm">
        <ul className="mb-3 divide-y divide-border">
          {cats.data?.map((c) => <li key={c.id} className="flex justify-between py-2"><span>{c.name}</span><button className="text-danger" onClick={() => delCat.mutate({ id: c.id })} aria-label={`Hapus ${c.name}`}><Trash2 className="h-4 w-4" /></button></li>)}
        </ul>
        <div className="flex gap-2"><Input value={catName} onChange={(e) => setCatName(e.target.value)} placeholder="Kategori baru" /><Button disabled={!catName} onClick={() => addCat.mutate({ name: catName })}>Tambah</Button></div>
      </Modal>
    </div>
  );
}

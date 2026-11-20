"use client";

import { Pencil, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Textarea } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { useApiMutation, usePaged } from "@/hooks/useApi";
import { useCan } from "@/lib/permissions";

type Supplier = { id: number; name: string; phone: string | null; address: string | null; note: string | null };

export default function SupplierPage() {
  const can = useCan();
  const [page, setPage] = useState(1);
  const list = usePaged<Supplier>(["suppliers"], "/suppliers", { page });
  const [edit, setEdit] = useState<Partial<Supplier> | null>(null);
  const save = useApiMutation<Partial<Supplier>>(edit?.id ? "put" : "post", (v) => (v.id ? `/suppliers/${v.id}` : "/suppliers"), [["suppliers"]], () => setEdit(null));
  const del = useApiMutation<{ id: number }>("delete", (v) => `/suppliers/${v.id}`, [["suppliers"]]);
  const columns: Column<Supplier>[] = [
    { header: "Nama", cell: (s) => <b>{s.name}</b> },
    { header: "Telepon", cell: (s) => s.phone ?? "-" },
    { header: "Alamat", cell: (s) => s.address ?? "-" },
    { header: "", className: "text-right", cell: (s) => can("supplier.manage") && <div className="flex justify-end gap-1"><Button size="sm" variant="ghost" onClick={() => setEdit(s)} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button><Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus ${s.name}?`, danger: true }); if (r.ok) del.mutate({ id: s.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button></div> },
  ];
  return (
    <div>
      <PageHeader title="Supplier" actions={can("supplier.manage") && <Button onClick={() => setEdit({})}><Plus className="h-4 w-4" /> Supplier</Button>} />
      <Card><DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} /></Card>
      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit?.id ? "Ubah supplier" : "Supplier baru"} size="sm" footer={<Button loading={save.isPending} onClick={() => edit && save.mutate(edit)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-3">
            <Field label="Nama *"><Input value={edit.name ?? ""} onChange={(e) => setEdit({ ...edit, name: e.target.value })} /></Field>
            <Field label="Telepon"><Input value={edit.phone ?? ""} onChange={(e) => setEdit({ ...edit, phone: e.target.value })} /></Field>
            <Field label="Alamat"><Textarea value={edit.address ?? ""} onChange={(e) => setEdit({ ...edit, address: e.target.value })} /></Field>
          </div>
        )}
      </Modal>
    </div>
  );
}

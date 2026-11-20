"use client";

import { MessageCircle, Plus } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Select, Textarea } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { useApiMutation, usePaged } from "@/hooks/useApi";
import { useDebounce } from "@/hooks/useDebounce";
import { fdate, num, rp } from "@/lib/format";
import { useCan } from "@/lib/permissions";
import type { Customer } from "@/types";


export default function PelangganPage() {
  const can = useCan();
  const router = useRouter();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("name");
  const s = useDebounce(search);
  const list = usePaged<Customer>(["customers"], "/customers", { page, search: s || undefined, sort });
  const [edit, setEdit] = useState<Partial<Customer> | null>(null);
  const save = useApiMutation<Partial<Customer>>("post", "/customers", [["customers"]], () => setEdit(null));
  const columns: Column<Customer>[] = [
    { header: "Nama", cell: (c) => <b>{c.name}</b> },
    { header: "WhatsApp", cell: (c) => <a href={`https://wa.me/${c.phone}`} target="_blank" rel="noopener" onClick={(e) => e.stopPropagation()} className="inline-flex items-center gap-1 text-primary hover:underline"><MessageCircle className="h-3.5 w-3.5" />{c.phone}</a> },
    { header: "Kunjungan", cell: (c) => num(c.visit_count) },
    { header: "Total belanja", cell: (c) => rp(c.total_spent) },
    { header: "Poin", cell: (c) => <b className="text-primary">{num(c.points)}</b> },
    { header: "Terakhir", cell: (c) => fdate(c.last_visit_at) },
  ];
  return (
    <div>
      <PageHeader title="Pelanggan" description="Pelanggan otomatis tercatat dari nomor WA saat memesan." actions={can("customer.manage") && <Button onClick={() => setEdit({})}><Plus className="h-4 w-4" /> Pelanggan</Button>} />
      <Card>
        <div className="flex flex-col gap-2 border-b border-border p-4 sm:flex-row">
          <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Cari nama / nomor..." className="flex-1" />
          <Select value={sort} onChange={(e) => setSort(e.target.value)} className="sm:w-52" aria-label="Urutkan">
            <option value="name">Urut nama</option>
            <option value="spent">Belanja terbanyak</option>
          </Select>
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} onRowClick={(c) => router.push(`/pelanggan/detail/?id=${c.id}`)} />
      </Card>
      <Modal open={!!edit} onClose={() => setEdit(null)} title="Pelanggan baru" size="sm" footer={<Button loading={save.isPending} onClick={() => edit && save.mutate(edit)}>Simpan</Button>}>
        {edit && (
          <div className="space-y-3">
            <Field label="Nama *"><Input value={edit.name ?? ""} onChange={(e) => setEdit({ ...edit, name: e.target.value })} /></Field>
            <Field label="Nomor WA *"><Input type="tel" value={edit.phone ?? ""} onChange={(e) => setEdit({ ...edit, phone: e.target.value })} placeholder="08xxxxxxxxxx" /></Field>
            <Field label="Email"><Input type="email" value={edit.email ?? ""} onChange={(e) => setEdit({ ...edit, email: e.target.value })} /></Field>
            <Field label="Catatan"><Textarea value={edit.note ?? ""} onChange={(e) => setEdit({ ...edit, note: e.target.value })} /></Field>
          </div>
        )}
      </Modal>
    </div>
  );
}

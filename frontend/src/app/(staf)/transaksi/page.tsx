"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Badge, Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Select } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { usePaged } from "@/hooks/useApi";
import { useDebounce } from "@/hooks/useDebounce";
import { BILL_STATUS, ORDER_TYPE, daysAgo, fdatetime, rp, today } from "@/lib/format";
import type { Bill } from "@/types";

export default function TransaksiPage() {
  const router = useRouter();
  const [page, setPage] = useState(1);
  const [from, setFrom] = useState(daysAgo(6));
  const [to, setTo] = useState(today());
  const [status, setStatus] = useState("");
  const [type, setType] = useState("");
  const [search, setSearch] = useState("");
  const s = useDebounce(search);
  const list = usePaged<Bill>(["bills-history"], "/bills", { page, from, to, status: status || undefined, type: type || undefined, search: s || undefined });
  const columns: Column<Bill>[] = [
    { header: "Invoice", cell: (b) => <b>{b.invoice_no}</b> },
    { header: "Waktu", cell: (b) => fdatetime(b.paid_at ?? b.opened_at) },
    { header: "Meja/Antrean", cell: (b) => b.label },
    { header: "Tipe", cell: (b) => ORDER_TYPE[b.type] },
    { header: "Pelanggan", cell: (b) => b.customer_name ?? "-" },
    { header: "Total", cell: (b) => <b>{rp(b.total)}</b> },
    { header: "Status", cell: (b) => <Badge className={BILL_STATUS[b.status]?.className}>{BILL_STATUS[b.status]?.text}</Badge> },
    { header: "Kasir", cell: (b) => b.closed_by ?? "-" },
  ];
  return (
    <div>
      <PageHeader title="Riwayat Transaksi" />
      <Card>
        <div className="grid gap-2 border-b border-border p-4 sm:grid-cols-2 lg:grid-cols-5">
          <Field label="Dari"><Input type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1); }} /></Field>
          <Field label="Sampai"><Input type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1); }} /></Field>
          <Field label="Status">
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
              <option value="">Semua</option>
              {Object.entries(BILL_STATUS).map(([k, v]) => <option key={k} value={k}>{v.text}</option>)}
            </Select>
          </Field>
          <Field label="Tipe">
            <Select value={type} onChange={(e) => { setType(e.target.value); setPage(1); }}>
              <option value="">Semua</option>
              {Object.entries(ORDER_TYPE).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </Select>
          </Field>
          <Field label="Cari"><SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Invoice / nama / WA" /></Field>
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} onRowClick={(b) => router.push(`/kasir/bill/?id=${b.id}`)} />
      </Card>
    </div>
  );
}

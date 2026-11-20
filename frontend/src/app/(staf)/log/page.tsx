"use client";

import { useState } from "react";
import { Badge, Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Select } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { usePaged } from "@/hooks/useApi";
import { fdatetime } from "@/lib/format";

type Log = { id: number; action: string; description: string | null; user: string | null; subject_type: string | null; subject_id: number | null; before: unknown; after: unknown; reason: string | null; ip: string | null; created_at: string };
const ACTIONS: Record<string, string> = { auth: "Login", "bill.void": "Void", "bill.refund": "Refund", "bill.discount": "Diskon", "order.cancel_item": "Hapus item", "menu.price_change": "Ubah harga", stock: "Stok", setting: "Pengaturan", user: "Staf", role: "Role", shift: "Shift", payment: "Pembayaran" };

export default function LogPage() {
  const [page, setPage] = useState(1);
  const [action, setAction] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [detail, setDetail] = useState<Log | null>(null);
  const list = usePaged<Log>(["logs"], "/activity-logs", { page, action: action || undefined, from: from || undefined, to: to || undefined });
  const columns: Column<Log>[] = [
    { header: "Waktu", cell: (l) => <span className="whitespace-nowrap">{fdatetime(l.created_at)}</span> },
    { header: "Pengguna", cell: (l) => l.user ?? "Sistem/Publik" },
    { header: "Aksi", cell: (l) => <Badge className="bg-surface-2 font-mono text-muted">{l.action}</Badge> },
    { header: "Keterangan", cell: (l) => l.description },
    { header: "Alasan", cell: (l) => <span className="text-muted">{l.reason ?? "-"}</span> },
    { header: "IP", cell: (l) => <span className="text-xs text-muted">{l.ip}</span> },
  ];
  return (
    <div>
      <PageHeader title="Log Aktivitas" description="Siapa melakukan apa dan kapan, termasuk nilai sebelum/sesudah." />
      <Card>
        <div className="flex flex-wrap gap-2 border-b border-border p-4">
          <Field label="Jenis aksi">
            <Select value={action} onChange={(e) => { setAction(e.target.value); setPage(1); }}>
              <option value="">Semua</option>
              {Object.entries(ACTIONS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </Select>
          </Field>
          <Field label="Dari"><Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></Field>
          <Field label="Sampai"><Input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
        </div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} onRowClick={setDetail} />
      </Card>
      <Modal open={!!detail} onClose={() => setDetail(null)} title={detail?.action} description={detail ? `${fdatetime(detail.created_at)} · ${detail.user ?? "Sistem"}` : ""} size="lg">
        {detail && (
          <div className="grid gap-3 sm:grid-cols-2">
            <div><p className="mb-1 text-sm font-semibold">Sebelum</p><pre className="overflow-auto rounded-xl bg-surface-2 p-3 text-xs">{JSON.stringify(detail.before, null, 2) ?? "-"}</pre></div>
            <div><p className="mb-1 text-sm font-semibold">Sesudah</p><pre className="overflow-auto rounded-xl bg-surface-2 p-3 text-xs">{JSON.stringify(detail.after, null, 2) ?? "-"}</pre></div>
          </div>
        )}
      </Modal>
    </div>
  );
}

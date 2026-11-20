"use client";

import { useQuery } from "@tanstack/react-query";
import { FileSpreadsheet, FileText } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Donut, SimpleBar } from "@/components/charts/charts";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, EmptyState, Skeleton } from "@/components/ui/card";
import { Field, Input, Select } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { api, download, errMsg } from "@/lib/api";
import { cn } from "@/lib/cn";
import { daysAgo, num, rp, today } from "@/lib/format";

type Report = {
  type: string; title: string; period: { from: string; to: string };
  columns: [string, string, "text" | "money" | "number"][];
  rows: Record<string, string | number | null>[];
  summary: [string, string | number, "text" | "money" | "number"][];
  chart?: { x: string; y: string; kind: "bar" | "pie"; limit?: number };
};

function fmt(v: unknown, t: string) {
  if (v === null || v === undefined || v === "") return "-";
  if (t === "money") return rp(Number(v));
  if (t === "number") return num(Number(v), 1);
  return String(v);
}

const PRESETS = [
  { label: "Hari ini", from: () => today(), to: () => today() },
  { label: "7 hari", from: () => daysAgo(6), to: () => today() },
  { label: "30 hari", from: () => daysAgo(29), to: () => today() },
  { label: "Bulan ini", from: () => today().slice(0, 8) + "01", to: () => today() },
];

export default function LaporanPage() {
  const types = useQuery({ queryKey: ["report-types"], queryFn: async () => (await api.get<{ data: { key: string; title: string }[] }>("/reports")).data.data });
  const [type, setType] = useState("sales");
  const [from, setFrom] = useState(daysAgo(6));
  const [to, setTo] = useState(today());
  const [group, setGroup] = useState("day");
  const params = { from, to, group };
  const q = useQuery({ queryKey: ["report", type, params], queryFn: async () => (await api.get<{ data: Report }>(`/reports/${type}`, { params })).data.data, placeholderData: (p) => p });
  const [exporting, setExporting] = useState<string | null>(null);
  const exp = async (format: "xlsx" | "pdf") => {
    setExporting(format);
    try {
      await download(`/reports/${type}`, { ...params, format }, `laporan-${type}-${from}_${to}.${format}`);
    } catch (e) {
      toast.error(errMsg(e, "Gagal mengunduh laporan."));
    } finally {
      setExporting(null);
    }
  };
  const r = q.data;
  const chartRows = r?.chart ? r.rows.slice(0, r.chart.limit ?? 40) : [];

  return (
    <div>
      <PageHeader title="Laporan" actions={<><Button variant="outline" loading={exporting === "xlsx"} onClick={() => exp("xlsx")}><FileSpreadsheet className="h-4 w-4" /> Excel</Button><Button variant="outline" loading={exporting === "pdf"} onClick={() => exp("pdf")}><FileText className="h-4 w-4" /> PDF</Button></>} />
      <div className="grid gap-4 lg:grid-cols-[240px_1fr]">
        <Card className="h-fit p-2">
          <nav aria-label="Jenis laporan" className="flex gap-1 overflow-x-auto lg:flex-col">
            {types.data?.map((t) => (
              <button key={t.key} onClick={() => setType(t.key)} className={cn("shrink-0 rounded-xl px-3 py-2 text-left text-sm font-medium", type === t.key ? "bg-primary text-primary-fg" : "text-muted hover:bg-surface-2 hover:text-fg")}>{t.title}</button>
            ))}
          </nav>
        </Card>
        <div className="min-w-0 space-y-4">
          <Card className="flex flex-wrap items-end gap-2 p-4">
            <Field label="Dari"><Input type="date" value={from} max={to} onChange={(e) => setFrom(e.target.value)} /></Field>
            <Field label="Sampai"><Input type="date" value={to} min={from} onChange={(e) => setTo(e.target.value)} /></Field>
            {type === "sales" && (
              <Field label="Kelompok">
                <Select value={group} onChange={(e) => setGroup(e.target.value)}><option value="day">Harian</option><option value="week">Mingguan</option><option value="month">Bulanan</option></Select>
              </Field>
            )}
            <div className="flex flex-wrap gap-1">
              {PRESETS.map((p) => <Button key={p.label} size="sm" variant="ghost" onClick={() => { setFrom(p.from()); setTo(p.to()); }}>{p.label}</Button>)}
            </div>
          </Card>

          {!r ? <Skeleton className="h-96" /> : (
            <>
              {r.summary.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  {r.summary.map(([l, v, t]) => (
                    <Card key={l} className="p-4"><p className="text-sm text-muted">{l}</p><p className="mt-1 text-xl font-bold">{fmt(v, t)}</p></Card>
                  ))}
                </div>
              )}
              {r.chart && chartRows.length > 0 && (
                <Card>
                  <CardHeader title={r.title} description={r.columns.find((c) => c[0] === r.chart!.y)?.[1]} />
                  <div className="p-4">
                    {r.chart.kind === "pie" ? (
                      <Donut data={chartRows.map((x) => ({ name: String(x[r.chart!.x]), total: Number(x[r.chart!.y]) }))} money={r.columns.find((c) => c[0] === r.chart!.y)?.[2] === "money"} />
                    ) : (
                      <SimpleBar data={chartRows} x={r.chart.x} y={r.chart.y} money={r.columns.find((c) => c[0] === r.chart!.y)?.[2] === "money"} horizontal={chartRows.length > 0 && String(chartRows[0][r.chart.x]).length > 6 && r.type !== "sales"} height={chartRows.length > 0 && String(chartRows[0][r.chart.x]).length > 6 && r.type !== "sales" ? Math.max(180, chartRows.length * 30) : 260} />
                    )}
                  </div>
                </Card>
              )}
              <Card>
                <CardHeader title={`${r.title} · tabel`} description={`${r.rows.length} baris`} />
                {r.rows.length === 0 ? <EmptyState title="Tidak ada data pada periode ini" /> : (
                  <div className="scrollbar-thin overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead><tr className="border-b border-border text-left text-xs uppercase text-muted">{r.columns.map((c) => <th key={c[0]} className={cn("whitespace-nowrap px-4 py-3", c[2] !== "text" && "text-right")}>{c[1]}</th>)}</tr></thead>
                      <tbody>
                        {r.rows.map((row, i) => (
                          <tr key={i} className={cn("border-b border-border/60", (row.kind === "subtotal" || row.kind === "total") && "bg-surface-2 font-bold")}>
                            {r.columns.map((c) => <td key={c[0]} className={cn("whitespace-nowrap px-4 py-2.5", c[2] !== "text" && "text-right tabular-nums", c[2] === "money" && Number(row[c[0]]) < 0 && "text-danger")}>{fmt(row[c[0]], c[2])}</td>)}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </Card>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

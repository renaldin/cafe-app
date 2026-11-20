"use client";

import { Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { num, rp } from "@/lib/format";

/** Urutan warna kategori tetap (tervalidasi CVD untuk mode terang & gelap) â€” lihat globals.css */
export const SERIES = ["var(--series-1)", "var(--series-2)", "var(--series-3)", "var(--series-4)", "var(--series-5)", "var(--series-6)"];

function compact(v: number) {
  if (Math.abs(v) >= 1_000_000) return `${num(v / 1_000_000, 1)}jt`;
  if (Math.abs(v) >= 1_000) return `${num(v / 1_000, 0)}rb`;
  return num(v);
}

type TipProps = { active?: boolean; payload?: { value: number; payload: Record<string, unknown> }[]; label?: string; money?: boolean; unit?: string };
function Tip({ active, payload, label, money, unit }: TipProps) {
  if (!active || !payload?.length) return null;
  const v = payload[0].value;
  return (
    <div className="rounded-xl border border-border bg-surface px-3 py-2 text-sm shadow-lg">
      <p className="text-xs text-muted">{label}</p>
      <p className="font-semibold text-fg">{money ? rp(v) : `${num(v, 1)}${unit ? ` ${unit}` : ""}`}</p>
    </div>
  );
}

/** Bar satu seri (tanpa legend; judul kartu menamai seri) */
export function SimpleBar({ data, x, y, money, height = 260, unit, horizontal, formatX }: { data: object[]; x: string; y: string; money?: boolean; height?: number; unit?: string; horizontal?: boolean; formatX?: (v: string) => string }) {
  return (
    <div style={{ height }} className="w-full">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} layout={horizontal ? "vertical" : "horizontal"} margin={{ top: 8, right: 12, left: horizontal ? 8 : 0, bottom: 0 }} barCategoryGap={horizontal ? 6 : "20%"}>
          <CartesianGrid stroke="var(--border)" strokeDasharray="0" vertical={horizontal} horizontal={!horizontal} />
          {horizontal ? (
            <>
              <XAxis type="number" tickFormatter={(v) => (money ? compact(v) : num(v))} tick={{ fill: "var(--muted)", fontSize: 11 }} axisLine={false} tickLine={false} />
              <YAxis type="category" dataKey={x} width={130} tick={{ fill: "var(--fg)", fontSize: 12 }} axisLine={false} tickLine={false} />
            </>
          ) : (
            <>
              <XAxis dataKey={x} tickFormatter={formatX} tick={{ fill: "var(--muted)", fontSize: 11 }} axisLine={{ stroke: "var(--border)" }} tickLine={false} interval="preserveStartEnd" minTickGap={8} />
              <YAxis tickFormatter={(v) => (money ? compact(v) : num(v))} tick={{ fill: "var(--muted)", fontSize: 11 }} axisLine={false} tickLine={false} width={44} />
            </>
          )}
          <Tooltip cursor={{ fill: "var(--surface-2)" }} content={<Tip money={money} unit={unit} />} labelFormatter={formatX as never} />
          <Bar dataKey={y} fill="var(--primary)" radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]} maxBarSize={36} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

/** Donut komposisi + legend bernilai (identitas tidak hanya lewat warna) */
export function Donut({ data, money = true }: { data: { name: string; total: number }[]; money?: boolean }) {
  const top = data.slice(0, 5);
  const rest = data.slice(5).reduce((s, d) => s + d.total, 0);
  const rows = rest > 0 ? [...top, { name: "Lainnya", total: rest }] : top;
  const sum = rows.reduce((s, d) => s + d.total, 0) || 1;
  return (
    <div className="flex flex-col items-center gap-4 sm:flex-row">
      <div className="h-44 w-44 shrink-0">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie data={rows} dataKey="total" nameKey="name" innerRadius="62%" outerRadius="100%" stroke="var(--surface)" strokeWidth={2} paddingAngle={1}>
              {rows.map((_, i) => <Cell key={i} fill={SERIES[i % SERIES.length]} />)}
            </Pie>
            <Tooltip content={({ active, payload }) => (active && payload?.length ? <div className="rounded-xl border border-border bg-surface px-3 py-2 text-sm shadow-lg"><p className="text-xs text-muted">{payload[0].name}</p><p className="font-semibold">{money ? rp(Number(payload[0].value)) : num(Number(payload[0].value))}</p></div> : null)} />
          </PieChart>
        </ResponsiveContainer>
      </div>
      <ul className="w-full space-y-1.5 text-sm">
        {rows.map((d, i) => (
          <li key={d.name} className="flex items-center gap-2">
            <span className="h-3 w-3 shrink-0 rounded-sm" style={{ background: SERIES[i % SERIES.length] }} aria-hidden />
            <span className="flex-1 truncate">{d.name}</span>
            <span className="text-muted">{Math.round((d.total / sum) * 100)}%</span>
            <span className="w-24 text-right font-medium">{money ? rp(d.total) : num(d.total)}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

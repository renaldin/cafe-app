"use client";

import { tableFeatures, useTable, type ColumnDef } from "@tanstack/react-table";
import { ChevronLeft, ChevronRight, Inbox } from "lucide-react";
import { useMemo } from "react";
import { cn } from "@/lib/cn";
import { Button } from "./button";
import { EmptyState, Skeleton } from "./card";

/** Definisi kolom sederhana yang dipakai semua halaman */
export type Column<T> = {
  header: React.ReactNode;
  cell: (row: T) => React.ReactNode;
  className?: string;
};

type Meta = { current_page: number; last_page: number; total: number; per_page: number };

const features = tableFeatures({});
const EMPTY: never[] = [];

export function DataTable<T extends object>({
  columns,
  data,
  loading,
  meta,
  onPage,
  empty = "Belum ada data",
  onRowClick,
}: {
  columns: Column<T>[];
  data: T[] | undefined;
  loading?: boolean;
  meta?: Meta;
  onPage?: (p: number) => void;
  empty?: string;
  onRowClick?: (row: T) => void;
}) {
  const defs = useMemo(
    () =>
      columns.map(
        (c, i) =>
          ({
            id: `c${i}`,
            header: () => c.header,
            cell: (ctx: { row: { original: T } }) => c.cell(ctx.row.original),
          }) as unknown as ColumnDef<typeof features, T>,
      ),
    [columns],
  );
  const table = useTable({ features, columns: defs, data: data ?? (EMPTY as T[]) });

  return (
    <div>
      <div className="scrollbar-thin overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted">
              {columns.map((c, i) => (
                <th key={i} className={cn("whitespace-nowrap px-4 py-3 font-semibold", c.className)}>
                  {c.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {loading &&
              Array.from({ length: 5 }).map((_, i) => (
                <tr key={i} className="border-b border-border/60">
                  {columns.map((_, j) => (
                    <td key={j} className="px-4 py-3">
                      <Skeleton className="h-4 w-full" />
                    </td>
                  ))}
                </tr>
              ))}
            {!loading &&
              table.getRowModel().rows.map((row) => (
                <tr
                  key={row.id}
                  onClick={onRowClick ? () => onRowClick(row.original) : undefined}
                  className={cn("border-b border-border/60 transition-colors last:border-0", onRowClick && "cursor-pointer hover:bg-surface-2")}
                >
                  {row.getAllCells().map((cell, j) => (
                    <td key={cell.id} className={cn("px-4 py-3 align-middle", columns[j]?.className)}>
                      <table.FlexRender cell={cell} />
                    </td>
                  ))}
                </tr>
              ))}
          </tbody>
        </table>
      </div>
      {!loading && (data?.length ?? 0) === 0 && <EmptyState icon={<Inbox className="h-6 w-6" />} title={empty} />}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between gap-2 border-t border-border px-4 py-3 text-sm">
          <span className="text-muted">
            Halaman {meta.current_page} dari {meta.last_page} · {meta.total} data
          </span>
          <div className="flex gap-1">
            <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => onPage?.(meta.current_page - 1)} aria-label="Halaman sebelumnya">
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button size="sm" variant="outline" disabled={meta.current_page >= meta.last_page} onClick={() => onPage?.(meta.current_page + 1)} aria-label="Halaman berikutnya">
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

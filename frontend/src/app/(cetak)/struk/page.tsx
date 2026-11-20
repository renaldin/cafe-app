"use client";

import { useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect } from "react";
import { Receipt, type ReceiptStore } from "@/components/staff/receipt";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/card";
import { api } from "@/lib/api";
import type { Bill } from "@/types";

function StrukContent() {
  const id = useSearchParams().get("id");
  const q = useQuery({
    queryKey: ["receipt", id],
    enabled: !!id,
    queryFn: async () => (await api.get<{ data: { bill: Bill; store: ReceiptStore; cashier: string } }>(`/bills/${id}/receipt`)).data.data,
  });
  useEffect(() => {
    if (q.data) {
      const t = setTimeout(() => window.print(), 400);
      return () => clearTimeout(t);
    }
  }, [q.data]);
  if (!q.data) return <div className="flex min-h-screen items-center justify-center"><Spinner /></div>;
  return (
    <div className="min-h-screen bg-white py-4">
      <style>{`@media print { @page { size: ${q.data.store.paper === "80" ? "80mm" : "58mm"} auto; margin: 0 } }`}</style>
      <div className="no-print mb-4 flex justify-center gap-2">
        <Button onClick={() => window.print()}><Printer className="h-4 w-4" /> Cetak</Button>
        <Button variant="outline" onClick={() => window.close()}>Tutup</Button>
      </div>
      <Receipt bill={q.data.bill} store={q.data.store} cashier={q.data.cashier} />
    </div>
  );
}

export default function StrukPage() {
  return (
    <Suspense fallback={null}>
      <StrukContent />
    </Suspense>
  );
}

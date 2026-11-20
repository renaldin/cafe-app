import { ShieldAlert } from "lucide-react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, EmptyState } from "@/components/ui/card";

export function Forbidden() {
  return (
    <Card>
      <EmptyState
        icon={<ShieldAlert className="h-6 w-6" />}
        title="Akses ditolak"
        description="Role Anda tidak memiliki izin untuk membuka halaman ini. Hubungi Owner atau Manajer bila perlu akses."
        action={
          <Link href="/">
            <Button variant="outline">Kembali ke beranda</Button>
          </Link>
        }
      />
    </Card>
  );
}

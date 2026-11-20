"use client";

import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardHeader } from "@/components/ui/card";
import { Field, Input } from "@/components/ui/input";
import { PageHeader } from "@/components/ui/page-header";
import { api, errMsg, fieldErrors } from "@/lib/api";
import { useAuth } from "@/stores/auth";

export default function AkunPage() {
  const user = useAuth((s) => s.user);
  const [form, setForm] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await api.put("/auth/password", form);
      toast.success("Password berhasil diganti.");
      setForm({ current_password: "", password: "", password_confirmation: "" });
    } catch (err) {
      setErrors(fieldErrors(err));
      toast.error(errMsg(err));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="mx-auto max-w-xl">
      <PageHeader title="Akun Saya" description={`${user?.name} · ${user?.roles?.join(", ")}`} />
      <Card>
        <CardHeader title="Ganti password" description="Gunakan minimal 8 karakter. Sesi di perangkat lain akan dikeluarkan." />
        <form onSubmit={submit} className="space-y-4 p-5">
          <Field label="Password saat ini" error={errors.current_password}>
            <Input type="password" autoComplete="current-password" value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} />
          </Field>
          <Field label="Password baru" error={errors.password}>
            <Input type="password" autoComplete="new-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
          </Field>
          <Field label="Ulangi password baru">
            <Input type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} />
          </Field>
          <Button type="submit" loading={saving}>
            Simpan password
          </Button>
        </form>
      </Card>
    </div>
  );
}

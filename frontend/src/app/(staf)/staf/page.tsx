"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient, useMutation } from "@tanstack/react-query";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Badge, Card } from "@/components/ui/card";
import { confirm } from "@/components/ui/confirm";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Input, Select, Switch } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/page-header";
import { SearchInput } from "@/components/ui/search-input";
import { useApiMutation, useGet, usePaged } from "@/hooks/useApi";
import { useDebounce } from "@/hooks/useDebounce";
import { api, errMsg, fieldErrors } from "@/lib/api";
import { fdatetime } from "@/lib/format";

type Staff = { id: number; name: string; username: string; email: string | null; phone: string | null; is_active: boolean; last_login_at: string | null; roles: string[] };
const schema = z.object({
  name: z.string().min(2, "Nama wajib diisi"),
  username: z.string().min(3, "Minimal 3 karakter").regex(/^[a-zA-Z0-9_-]+$/, "Hanya huruf, angka, - dan _"),
  email: z.string().email("Email tidak valid").or(z.literal("")).optional(),
  phone: z.string().optional(),
  password: z.string().optional(),
  role: z.string().min(1, "Pilih role"),
  is_active: z.boolean(),
});
type Form = z.infer<typeof schema>;

export default function StafPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const s = useDebounce(search);
  const list = usePaged<Staff>(["users"], "/users", { page, search: s || undefined });
  const roles = useGet<{ id: number; name: string }[]>(["roles-list"], "/roles/list");
  const [edit, setEdit] = useState<Staff | "new" | null>(null);
  const del = useApiMutation<{ id: number }>("delete", (v) => `/users/${v.id}`, [["users"]]);
  const { register, handleSubmit, reset, control, setError, formState } = useForm<Form>({ resolver: zodResolver(schema) });

  useEffect(() => {
    if (edit === "new") reset({ name: "", username: "", email: "", phone: "", password: "", role: "Kasir", is_active: true });
    else if (edit) reset({ name: edit.name, username: edit.username, email: edit.email ?? "", phone: edit.phone ?? "", password: "", role: edit.roles[0] ?? "", is_active: edit.is_active });
  }, [edit, reset]);

  const save = useMutation({
    mutationFn: async (v: Form) => {
      const body = { ...v, email: v.email || null, password: v.password || undefined };
      if (edit === "new" && (!v.password || v.password.length < 8)) throw Object.assign(new Error("pw"), { pw: true });
      return edit && edit !== "new" ? (await api.put(`/users/${edit.id}`, body)).data : (await api.post("/users", body)).data;
    },
    onSuccess: (r) => { toast.success(r.message); setEdit(null); void qc.invalidateQueries({ queryKey: ["users"] }); },
    onError: (e: unknown) => {
      if ((e as { pw?: boolean }).pw) return setError("password", { message: "Password minimal 8 karakter" });
      Object.entries(fieldErrors(e)).forEach(([k, m]) => setError(k as keyof Form, { message: m }));
      toast.error(errMsg(e));
    },
  });

  const columns: Column<Staff>[] = [
    { header: "Nama", cell: (u) => <div><b>{u.name}</b><p className="text-xs text-muted">@{u.username}</p></div> },
    { header: "Role", cell: (u) => u.roles.map((r) => <Badge key={r} className="bg-primary-soft text-primary">{r}</Badge>) },
    { header: "Status", cell: (u) => <Badge className={u.is_active ? "bg-success-soft text-success" : "bg-surface-3 text-muted"}>{u.is_active ? "Aktif" : "Nonaktif"}</Badge> },
    { header: "Login terakhir", cell: (u) => fdatetime(u.last_login_at) },
    { header: "", className: "text-right", cell: (u) => <div className="flex justify-end gap-1"><Button size="sm" variant="ghost" onClick={() => setEdit(u)} aria-label="Ubah"><Pencil className="h-4 w-4" /></Button><Button size="sm" variant="ghost" className="text-danger" onClick={async () => { const r = await confirm({ title: `Hapus ${u.name}?`, danger: true }); if (r.ok) del.mutate({ id: u.id }); }} aria-label="Hapus"><Trash2 className="h-4 w-4" /></Button></div> },
  ];

  return (
    <div>
      <PageHeader title="Staf" description="Kelola akun staf dan role-nya." actions={<Button onClick={() => setEdit("new")}><Plus className="h-4 w-4" /> Staf</Button>} />
      <Card>
        <div className="border-b border-border p-4"><SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Cari nama / username..." /></div>
        <DataTable columns={columns} data={list.data?.data} loading={list.isLoading} meta={list.data?.meta} onPage={setPage} />
      </Card>
      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit === "new" ? "Staf baru" : "Ubah staf"} footer={<Button loading={save.isPending} onClick={handleSubmit((v) => save.mutate(v))}>Simpan</Button>}>
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={handleSubmit((v) => save.mutate(v))} noValidate>
          <Field label="Nama *" error={formState.errors.name?.message}><Input {...register("name")} /></Field>
          <Field label="Username *" error={formState.errors.username?.message}><Input autoComplete="off" {...register("username")} /></Field>
          <Field label="Email" error={formState.errors.email?.message}><Input type="email" {...register("email")} /></Field>
          <Field label="Telepon"><Input {...register("phone")} /></Field>
          <Field label={edit === "new" ? "Password *" : "Password baru (opsional)"} error={formState.errors.password?.message}><Input type="password" autoComplete="new-password" {...register("password")} /></Field>
          <Field label="Role *" error={formState.errors.role?.message}>
            <Select {...register("role")}>{roles.data?.map((r) => <option key={r.id} value={r.name}>{r.name}</option>)}</Select>
          </Field>
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={!!field.value} onChange={field.onChange} label="Akun aktif" />} />
          <button type="submit" hidden />
        </form>
      </Modal>
    </div>
  );
}

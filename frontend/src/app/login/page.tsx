"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Coffee, Eye, EyeOff } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";
import { homeFor } from "@/components/layout/nav";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/input";
import { ThemeToggle } from "@/components/ui/theme-toggle";
import { api, errMsg } from "@/lib/api";
import { useAuth } from "@/stores/auth";

const schema = z.object({
  username: z.string().min(1, "Username wajib diisi"),
  password: z.string().min(1, "Password wajib diisi"),
});
type Form = z.infer<typeof schema>;

function LoginForm() {
  const router = useRouter();
  const params = useSearchParams();
  const setSession = useAuth((s) => s.setSession);
  const token = useAuth((s) => s.token);
  const hydrated = useAuth((s) => s.hydrated);
  const [show, setShow] = useState(false);
  const { register, handleSubmit, formState } = useForm<Form>({ resolver: zodResolver(schema) });

  useEffect(() => {
    if (params.get("expired")) toast.warning("Sesi Anda telah berakhir. Silakan login kembali.");
  }, [params]);

  useEffect(() => {
    if (hydrated && token && !params.get("expired")) router.replace("/");
  }, [hydrated, token, router, params]);

  const onSubmit = async (values: Form) => {
    try {
      const { data } = await api.post("/auth/login", { ...values, device_name: navigator.userAgent.slice(0, 90) });
      setSession(data.data);
      toast.success(`Halo, ${data.data.user.name}!`);
      const perms: string[] = data.data.permissions;
      const isOwner = (data.data.user.roles as string[]).includes("Owner");
      const next = params.get("next");
      router.replace(next && next.startsWith("/") && !next.startsWith("//") ? next : homeFor((p) => !p || isOwner || (Array.isArray(p) ? p : [p]).some((x) => perms.includes(x))));
    } catch (e) {
      toast.error(errMsg(e, "Gagal masuk."));
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
      <Field label="Username atau email" htmlFor="username" error={formState.errors.username?.message}>
        <Input id="username" autoComplete="username" autoFocus {...register("username")} />
      </Field>
      <Field label="Password" htmlFor="password" error={formState.errors.password?.message}>
        <div className="relative">
          <Input id="password" type={show ? "text" : "password"} autoComplete="current-password" className="pr-10" {...register("password")} />
          <button type="button" onClick={() => setShow((v) => !v)} className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted" aria-label={show ? "Sembunyikan password" : "Tampilkan password"}>
            {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </button>
        </div>
      </Field>
      <Button type="submit" size="lg" className="w-full" loading={formState.isSubmitting}>
        Masuk
      </Button>
    </form>
  );
}

export default function LoginPage() {
  return (
    <main className="relative flex min-h-screen items-center justify-center bg-gradient-to-br from-primary-soft via-bg to-bg px-4">
      <ThemeToggle className="absolute right-4 top-4" />
      <div className="card-shadow w-full max-w-sm rounded-3xl border border-border bg-surface p-7">
        <div className="mb-6 text-center">
          <span className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary text-primary-fg">
            <Coffee className="h-6 w-6" />
          </span>
          <h1 className="text-xl font-bold">Masuk Staf</h1>
          <p className="text-sm text-muted">Gunakan akun yang diberikan Owner/Manajer.</p>
        </div>
        <Suspense fallback={null}>
          <LoginForm />
        </Suspense>
      </div>
    </main>
  );
}

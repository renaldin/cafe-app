import { useAuth } from "@/stores/auth";

export function useCan() {
  const permissions = useAuth((s) => s.permissions);
  const roles = useAuth((s) => s.user?.roles ?? []);
  return (perm?: string | string[]) => {
    if (!perm) return true;
    if (roles.includes("Owner")) return true;
    const list = Array.isArray(perm) ? perm : [perm];
    return list.some((p) => permissions.includes(p));
  };
}

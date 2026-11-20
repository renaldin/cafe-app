import {
  BarChart3,
  BookOpen,
  ChefHat,
  ClipboardList,
  Coins,
  FileClock,
  History,
  LayoutDashboard,
  LayoutGrid,
  Layers,
  ListPlus,
  Package,
  Settings,
  ShieldCheck,
  ShoppingCart,
  SlidersHorizontal,
  Tag,
  Truck,
  UserCog,
  Users,
  Wallet,
  Warehouse,
  type LucideIcon,
} from "lucide-react";

export type NavItem = { href: string; label: string; icon: LucideIcon; perm?: string | string[] };
export type NavGroup = { label: string; items: NavItem[] };

export const NAV: NavGroup[] = [
  {
    label: "Operasional",
    items: [
      { href: "/dashboard/", label: "Dashboard", icon: LayoutDashboard, perm: "dashboard.view" },
      { href: "/kasir/", label: "Kasir", icon: ShoppingCart, perm: "bill.view" },
      { href: "/pesanan/baru/", label: "Pesanan Manual", icon: ListPlus, perm: "order.create" },
      { href: "/meja/", label: "Meja", icon: LayoutGrid, perm: "table.view" },
      { href: "/dapur/", label: "Dapur / Bar", icon: ChefHat, perm: "kds.view" },
      { href: "/shift/", label: "Shift Kasir", icon: Wallet, perm: "shift.manage" },
    ],
  },
  {
    label: "Katalog",
    items: [
      { href: "/menu/", label: "Menu", icon: BookOpen, perm: "menu.view" },
      { href: "/kategori/", label: "Kategori", icon: Layers, perm: "menu.manage" },
      { href: "/modifier/", label: "Opsi & Modifier", icon: SlidersHorizontal, perm: "menu.manage" },
    ],
  },
  {
    label: "Stok",
    items: [
      { href: "/bahan/", label: "Bahan Baku", icon: Package, perm: "stock.view" },
      { href: "/stok/pembelian/", label: "Pembelian", icon: Truck, perm: "stock.view" },
      { href: "/stok/opname/", label: "Stock Opname", icon: ClipboardList, perm: "stock.view" },
      { href: "/stok/mutasi/", label: "Mutasi & Waste", icon: Warehouse, perm: "stock.view" },
      { href: "/supplier/", label: "Supplier", icon: Truck, perm: "stock.view" },
    ],
  },
  {
    label: "Pelanggan & Promo",
    items: [
      { href: "/pelanggan/", label: "Pelanggan", icon: Users, perm: "customer.view" },
      { href: "/promo/", label: "Voucher & Promo", icon: Tag, perm: "promo.manage" },
    ],
  },
  {
    label: "Keuangan",
    items: [
      { href: "/transaksi/", label: "Riwayat Transaksi", icon: History, perm: "bill.view" },
      { href: "/biaya/", label: "Biaya Operasional", icon: Coins, perm: "expense.manage" },
      { href: "/laporan/", label: "Laporan", icon: BarChart3, perm: "report.view" },
    ],
  },
  {
    label: "Administrasi",
    items: [
      { href: "/staf/", label: "Staf", icon: UserCog, perm: "user.manage" },
      { href: "/role/", label: "Role & Akses", icon: ShieldCheck, perm: "role.manage" },
      { href: "/pengaturan/", label: "Pengaturan", icon: Settings, perm: "setting.manage" },
      { href: "/log/", label: "Log Aktivitas", icon: FileClock, perm: "log.view" },
    ],
  },
];

/** Halaman tambahan (tidak di sidebar) untuk breadcrumb & otorisasi */
export const EXTRA_PAGES: NavItem[] = [
  { href: "/kasir/bill/", label: "Detail Bill", icon: ShoppingCart, perm: "bill.view" },
  { href: "/menu/form/", label: "Form Menu", icon: BookOpen, perm: "menu.manage" },
  { href: "/pelanggan/detail/", label: "Detail Pelanggan", icon: Users, perm: "customer.view" },
  { href: "/stok/pembelian/baru/", label: "Pembelian Baru", icon: Truck, perm: "stock.manage" },
  { href: "/akun/", label: "Akun Saya", icon: UserCog },
];

export function findPage(pathname: string): { item: NavItem; group?: string; parent?: NavItem } | null {
  const p = pathname.endsWith("/") ? pathname : pathname + "/";
  for (const g of NAV) {
    const item = g.items.find((i) => i.href === p);
    if (item) return { item, group: g.label };
  }
  const extra = EXTRA_PAGES.find((i) => i.href === p);
  if (extra) {
    const parentHref = p.split("/").filter(Boolean).slice(0, -1).join("/");
    let parent: NavItem | undefined;
    let group: string | undefined;
    for (const g of NAV) {
      const found = g.items.find((i) => i.href === `/${parentHref}/`);
      if (found) {
        parent = found;
        group = g.label;
      }
    }
    return { item: extra, parent, group };
  }
  return null;
}

/** Halaman awal sesuai permission */
export function homeFor(can: (p?: string | string[]) => boolean): string {
  const order = ["/dashboard/", "/kasir/", "/dapur/", "/meja/", "/menu/"];
  for (const href of order) {
    const item = NAV.flatMap((g) => g.items).find((i) => i.href === href);
    if (item && can(item.perm)) return href;
  }
  const first = NAV.flatMap((g) => g.items).find((i) => can(i.perm));
  return first?.href ?? "/akun/";
}

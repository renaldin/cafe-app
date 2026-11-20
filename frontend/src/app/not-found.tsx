import Link from "next/link";

export default function NotFound() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center px-6 text-center">
      <p className="text-6xl font-black text-primary">404</p>
      <h1 className="mt-3 text-xl font-bold">Halaman tidak ditemukan</h1>
      <p className="mt-1 text-sm text-muted">Alamat yang Anda buka tidak tersedia.</p>
      <Link href="/" className="mt-6 rounded-xl bg-primary px-5 py-2.5 font-semibold text-primary-fg">
        Ke beranda
      </Link>
    </main>
  );
}

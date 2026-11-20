# ☕ Cafe POS — Aplikasi Manajemen Cafe

Aplikasi manajemen cafe lengkap: **pemesanan QR di meja, takeaway, kasir, layar dapur/bar (KDS), stok & resep, pelanggan & promo, dashboard, laporan**.

| Bagian | Teknologi | Lokasi deploy |
|---|---|---|
| `backend/` | Laravel 12 (REST API, Sanctum token, spatie/permission) | `api.domainanda.com` |
| `frontend/` | Next.js 16 (App Router, **static export**), Tailwind CSS 4, TanStack Query, Zustand | `app.domainanda.com` |

Dirancang untuk **shared hosting cPanel tanpa Node.js**: tanpa WebSocket/Redis/queue worker. Fitur "real-time" memakai polling ringan dengan parameter `since`.

---

## Daftar isi
1. [Persyaratan & menjalankan lokal](#1-persyaratan--menjalankan-lokal)
2. [Contoh konfigurasi .env](#2-contoh-konfigurasi-env)
3. [Deploy ke cPanel langkah demi langkah](#3-deploy-ke-cpanel-langkah-demi-langkah)
4. [QR meja & panduan per role](#4-qr-meja--panduan-singkat-per-role)
5. [Catatan performa shared hosting](#5-catatan-performa-shared-hosting)
6. [Checklist keamanan produksi](#6-checklist-keamanan-produksi)
7. [Troubleshooting](#7-troubleshooting)
8. [Referensi teknis (arsitektur, status, API)](#8-referensi-teknis)

---

## 1. Persyaratan & menjalankan lokal

### Persyaratan
- **PHP 8.2 – 8.4** dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `zip`, `intl`, `curl` (lokal juga `pdo_sqlite` untuk test)
- Composer 2
- MySQL 5.7+/MariaDB 10.3+ (produksi). Lokal bisa SQLite.
- Node.js 20+ (**hanya** di komputer Anda untuk build frontend; server tidak butuh Node)

### Backend
```bash
cd backend
composer install
cp .env.example .env          # lalu sesuaikan (lokal: DB_CONNECTION=sqlite)
php artisan key:generate
touch database/database.sqlite   # bila pakai SQLite
php artisan migrate --seed       # role, permission, owner + data demo (env local)
php artisan serve                # http://localhost:8000
```
Menjalankan test:
```bash
php artisan test
```

### Frontend
```bash
cd frontend
npm install
cp .env.example .env.local    # NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
npm run dev                   # http://localhost:3000
```

### Akun bawaan
| Username | Password | Role |
|---|---|---|
| `owner` | `GantiPassword123!` (atau nilai `OWNER_DEFAULT_PASSWORD`) | Owner |
| `manajer`, `kasir`, `pelayan`, `dapur`, `barista` | `password123` | **hanya data demo** (env `local`/`SEED_DEMO=true`) |

> ⚠️ **Wajib ganti password Owner** setelah login pertama (menu profil → *Akun & password*). Hapus akun demo di produksi.

---

## 2. Contoh konfigurasi .env

### Backend `backend/.env` (produksi)
```dotenv
APP_NAME="Cafe POS"
APP_ENV=production
APP_KEY=base64:xxxxxxxx           # php artisan key:generate
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://api.domainanda.com
FRONTEND_URL=https://app.domainanda.com    # CORS, boleh beberapa dipisah koma
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpaneluser_cafe
DB_USERNAME=cpaneluser_cafe
DB_PASSWORD=********
DB_TIMEZONE=+07:00

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=daily
LOG_LEVEL=error

OWNER_DEFAULT_PASSWORD=GantiPassword123!
SEED_DEMO=false

# Opsional: payment gateway (kosong = nonaktif & disembunyikan)
PAYMENT_GATEWAY=            # midtrans | xendit
MIDTRANS_SERVER_KEY=
MIDTRANS_PRODUCTION=false
XENDIT_SECRET_KEY=
XENDIT_CALLBACK_TOKEN=

# Opsional: lokasi upload bila folder public bukan document root
PUBLIC_UPLOAD_PATH=
PUBLIC_UPLOAD_URL=

# Installer tanpa SSH (isi >= 32 karakter acak, KOSONGKAN setelah dipakai)
SETUP_TOKEN=
```
Lihat `backend/.env.example` untuk versi lengkap.

### Frontend `frontend/.env.production`
```dotenv
NEXT_PUBLIC_API_URL=https://api.domainanda.com/api/v1
NEXT_PUBLIC_APP_URL=https://app.domainanda.com
```
> Semua kunci rahasia hanya ada di `.env` backend. Variabel `NEXT_PUBLIC_*` ikut ter-bundle ke browser — jangan isi rahasia di sana.

---

## 3. Deploy ke cPanel langkah demi langkah

### 3.1 Pilih versi PHP & ekstensi
cPanel → **Select PHP Version** → pilih **PHP 8.2/8.3** → tab *Extensions*, centang: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo, gd, zip, intl, curl`. Atur `upload_max_filesize` ≥ 8M dan `memory_limit` ≥ 256M (untuk export PDF/Excel).

### 3.2 Buat subdomain
cPanel → **Domains / Subdomains**:
- `api.domainanda.com` → document root: **`/home/USER/api.domainanda.com/public`** (folder `public` Laravel)
- `app.domainanda.com` → document root: `/home/USER/app.domainanda.com` (atau `public_html` bila frontend di domain utama)

> **Tidak bisa mengarahkan document root ke `/public`?** Upload seluruh isi `backend/` ke document root subdomain. File `backend/.htaccess` (sudah disediakan) meneruskan semua request ke `public/` dan memblokir akses ke `.env`, `composer.json`, dll. Folder `storage/` & `vendor/` juga sudah berisi `.htaccess` *deny*.

### 3.3 Buat database
cPanel → **MySQL® Databases**: buat database (mis. `user_cafe`), buat user, lalu **Add User to Database** dengan *ALL PRIVILEGES*.

### 3.4 Upload backend
1. Di komputer: `cd backend && composer install --no-dev --optimize-autoloader`
2. Zip folder `backend` (termasuk `vendor/`, tanpa `node_modules`, `.env`, `database/database.sqlite`).
3. cPanel → **File Manager** → upload ke `/home/USER/api.domainanda.com/` → *Extract*.
4. Salin `.env.example` menjadi `.env`, isi sesuai bagian 2. Buat `APP_KEY` dengan `php artisan key:generate` (atau lokal lalu salin nilainya).
5. Permission: folder `storage/` dan `bootstrap/cache/` **755** (beberapa host butuh 775). File `.env` **640**.

### 3.5 Migrate & seed
**Dengan Terminal/SSH** (cPanel → Terminal):
```bash
cd ~/api.domainanda.com
php artisan migrate --force
php artisan db:seed --force          # role, permission, akun owner (+ demo bila SEED_DEMO=true)
php artisan optimize                 # cache config & route
```
**Tanpa SSH** (alternatif aman, wajib dihapus setelah dipakai):
1. Isi `SETUP_TOKEN=` di `.env` dengan string acak ≥ 32 karakter.
2. Salin `deploy/setup.php` ke `public/setup.php`.
3. Buka berurutan:
   - `https://api.domainanda.com/setup.php?token=TOKEN&action=migrate`
   - `https://api.domainanda.com/setup.php?token=TOKEN&action=seed`
   - `https://api.domainanda.com/setup.php?token=TOKEN&action=optimize`
4. **Hapus `public/setup.php` dan kosongkan `SETUP_TOKEN`.**

### 3.6 Storage tanpa symlink
Aplikasi **tidak memakai `storage:link`**. Foto menu/logo disimpan langsung ke `public/uploads/` (sudah dikompres ke WebP). Bila folder `public` Laravel bukan document root (opsi alternatif 3.2), isi `PUBLIC_UPLOAD_PATH` & `PUBLIC_UPLOAD_URL` agar file tetap dapat diakses publik. Folder upload diproteksi `.htaccess` agar tidak bisa mengeksekusi PHP.

### 3.7 Cron job scheduler
cPanel → **Cron Jobs** → *Once Per Minute* (`* * * * *`):
```
/usr/local/bin/php /home/USER/api.domainanda.com/artisan schedule:run >> /dev/null 2>&1
```
(Cek path PHP: di Terminal jalankan `which php`, atau `/opt/cpanel/ea-php82/root/usr/bin/php`.)
Jadwal: hapus token kedaluwarsa, tandai pembayaran online kedaluwarsa, bersihkan cache/session, pangkas log lama.

### 3.8 Build & upload frontend
```bash
cd frontend
cp .env.production.example .env.production   # isi URL produksi
npm ci
npm run build                                # hasil: folder out/
```
Upload **isi folder `out/`** (termasuk `.htaccess` dan `404.html`) ke document root `app.domainanda.com`. File `.htaccess` menangani trailing slash dan fallback 404 agar refresh halaman tidak error.

### 3.9 CORS & SSL
- `FRONTEND_URL` di backend harus persis sama dengan origin frontend (`https://app.domainanda.com`, tanpa `/` di akhir). Setelah ubah `.env`: `php artisan optimize:clear && php artisan optimize`.
- cPanel → **SSL/TLS Status** → *Run AutoSSL* untuk kedua subdomain. Setelah aktif, buka komentar blok *Paksa HTTPS* di kedua `.htaccess`.

### 3.10 Payment gateway (opsional)
- **Midtrans**: isi `PAYMENT_GATEWAY=midtrans`, `MIDTRANS_SERVER_KEY`, `MIDTRANS_PRODUCTION`. Dashboard Midtrans → *Settings → Configuration* → **Payment Notification URL**: `https://api.domainanda.com/api/v1/public/webhooks/midtrans`. Signature diverifikasi `sha512(order_id+status_code+gross_amount+server_key)`.
- **Xendit**: isi `PAYMENT_GATEWAY=xendit`, `XENDIT_SECRET_KEY`, `XENDIT_CALLBACK_TOKEN`. Dashboard Xendit → *Settings → Webhooks* → *Invoices paid*: `https://api.domainanda.com/api/v1/public/webhooks/xendit`. Token verifikasi dicek dari header `x-callback-token`.
- Tanpa queue worker: status diperbarui oleh webhook, dan halaman pelacakan pelanggan juga memanggil endpoint cek status (dibatasi 1x/10 detik per pembayaran) sebagai cadangan.
- URL webhook aktif bisa dilihat di **Pengaturan → Sistem & backup**.

---

## 4. QR meja & panduan singkat per role

### Cetak & pasang QR
1. Login Owner/Manajer → **Meja** → **Cetak QR** (bisa semua meja sekaligus atau pilih tertentu).
2. Cetak di kertas tebal/stiker (A4, 6 QR per halaman), laminasi, tempel di meja.
3. QR berisi `https://app.domainanda.com/pesan/?t=<token acak 48 karakter>`.
4. Jika QR bocor/disalahgunakan: **Meja → pilih meja → Ganti QR**, lalu cetak ulang (QR lama langsung tidak berlaku).
5. Link takeaway untuk Instagram/WA: `https://app.domainanda.com/pesan/?mode=takeaway`.

### Alur pesanan
```
Pelanggan scan QR → pilih menu & opsi → Kirim
   └─(opsional: konfirmasi pelayan bila mode anti-prank aktif)
Dapur/Bar menerima kartu pesanan + bunyi notifikasi
   Baru → Diproses → Siap → Disajikan (ketuk item / tombol kartu)
Pelanggan memantau status di HP, bisa "Pesan lagi", "Panggil pelayan", "Minta bill"
Kasir: buka bill → (diskon/voucher/poin) → bayar → struk dicetak/kirim WA
Meja otomatis "Perlu Dibersihkan" → pelayan tandai "Sudah dibersihkan" → "Kosong"
```

### Per role
- **Owner** – semua akses: pengaturan, role & permission (bisa membuat role baru), laporan keuangan (laba rugi), log.
- **Manajer** – menu, stok, laporan operasional, void/refund, kelola staf.
- **Kasir** – mulai hari dengan **Buka shift** (isi modal). Layar **Kasir** menampilkan bill aktif, permintaan bill, konfirmasi pesanan, panggilan meja. Pembayaran tunai (tombol nominal cepat & kembalian otomatis), QRIS, transfer, kartu, split payment, bagi rata per orang. Akhiri dengan **Tutup shift** (hitung uang fisik → selisih tercatat).
- **Pelayan** – **Pesanan manual** untuk tamu tanpa HP, pantau **Meja**, konfirmasi pesanan QR (bila mode konfirmasi aktif), tandai meja bersih.
- **Dapur/Bar** – buka **Dapur / Bar** di tablet (tombol layar penuh), pilih stasiun. Ketuk item untuk memajukan status; kartu kuning/merah bila lewat target waktu. Tombol **Menu habis** untuk mematikan menu yang habis. Aktifkan **Suara** sekali per perangkat (aturan browser).

---

## 5. Catatan performa shared hosting

| Layar | Interval polling | Catatan |
|---|---|---|
| Dapur/Bar | 4 detik | berhenti saat tab tidak aktif |
| Kasir | 5 detik | |
| Meja | 8 detik | |
| Pelacakan pelanggan | 5 detik | |
| Dashboard | 30 detik | |

- Setiap polling mengirim `?since=<versi>`; jika tidak ada perubahan server cukup menjalankan beberapa query `MAX(updated_at)` dan membalas `{changed:false}` (±200 byte).
- Menu publik di-cache (versi + ETag) dan foto dikompres WebP ≤ 800px.
- **Rekomendasi shared hosting**: ± 5–8 perangkat staf aktif bersamaan + ± 30 pelanggan memantau pesanan, ≤ 300 transaksi/hari.
- **Pertimbangkan VPS** bila: lebih dari 2 cabang, > 10 perangkat staf aktif, > 500 transaksi/hari, sering terkena limit *entry processes*/CPU di cPanel, atau butuh notifikasi instan (WebSocket/Laravel Reverb) dan queue worker.

---

## 6. Checklist keamanan produksi
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`
- [ ] Ganti password Owner default, hapus akun demo, gunakan password ≥ 8 karakter
- [ ] `FRONTEND_URL` hanya berisi domain frontend resmi (CORS ketat)
- [ ] SSL aktif & redirect HTTPS dinyalakan di `.htaccess`
- [ ] Document root API mengarah ke `public/` (atau `.htaccess` alternatif dipakai)
- [ ] `public/setup.php` sudah dihapus dan `SETUP_TOKEN` kosong
- [ ] Permission `.env` 640, `storage/` & `bootstrap/cache/` 755
- [ ] Rate limit aktif (login 5x/menit per username+IP; endpoint publik per IP, per meja & per path) — bawaan
- [ ] Mode **konfirmasi pesanan** diaktifkan bila sering ada pesanan iseng
- [ ] Backup database & folder `public/uploads` **harian** (lihat Pengaturan → Sistem & backup)
- [ ] Kunci payment gateway hanya di `.env` backend, webhook terverifikasi signature

---

## 7. Troubleshooting

| Masalah | Penyebab & solusi |
|---|---|
| **Error 500** | Lihat `storage/logs/laravel-*.log`. Penyebab umum: `APP_KEY` kosong, permission `storage/` / `bootstrap/cache/`, ekstensi PHP belum aktif, versi PHP < 8.2. Jalankan `php artisan optimize:clear`. |
| **CORS error** di browser | `FRONTEND_URL` tidak sama persis dengan origin (http vs https, ada `/` di akhir, www). Setelah ubah: `php artisan optimize:clear`. Pastikan request ke `/api/v1/...` bukan `/v1/...`. |
| **Selalu 401 / logout sendiri** | Header Authorization dibuang server. `public/.htaccess` sudah meneruskannya; bila memakai `.htaccess` alternatif di root, pastikan baris `HTTP_AUTHORIZATION` ada. |
| **404 saat refresh halaman frontend** | File `.htaccess` dari `out/` belum ikut ter-upload (file tersembunyi — aktifkan *Show Hidden Files* di File Manager). |
| **Foto menu tidak muncul** | Cek folder `public/uploads/menus` ada & writable, `APP_URL` benar (https). Jika public bukan document root, isi `PUBLIC_UPLOAD_PATH/URL`. |
| **Polling lambat / server berat** | Kurangi jumlah tab terbuka (tiap tab = 1 polling), pastikan cron berjalan (membersihkan cache), aktifkan OPcache di Select PHP Version, gunakan `CACHE_STORE=file` bila tabel cache membesar. |
| **Notifikasi suara tidak bunyi** | Browser memblokir audio sebelum ada interaksi: klik tombol **Suara** sekali setelah membuka layar. |
| **Export PDF gagal** | Naikkan `memory_limit` (≥ 256M) dan `max_execution_time` (≥ 60). |
| **Jam/tanggal salah** | Pastikan `APP_TIMEZONE=Asia/Jakarta` dan `DB_TIMEZONE=+07:00`. |

---

## 8. Referensi teknis

### Struktur folder
```
backend/
  app/Enums/                 status pesanan/bill/meja (state machine)
  app/Services/              PricingService, OrderService, BillService, PaymentService, StockService,
                             RecipeCostService, LoyaltyService, VoucherService, ShiftService, ReportService
  app/Payments/              Midtrans & Xendit (opsional)
  app/Http/Controllers/Api/V1/{Public,Staff}
  app/Http/Requests/         Form Request validasi
  app/Http/Resources/        API Resource
  app/Support/               ApiResponse, Settings, Activity log, Permissions, Polling
  database/seeders/          role/permission, owner, data demo (25+ menu, 10 meja, bahan & resep)
  deploy/setup.php           installer tanpa SSH
  tests/Feature/             test alur kritis
frontend/
  src/app/pesan, lacak       halaman pelanggan (mobile-first)
  src/app/(staf)/...         dashboard, kasir, dapur, meja, menu, stok, laporan, pengaturan, dst.
  src/app/(cetak)/...        struk & cetak QR
  src/components, hooks, lib, stores
```

### Status pesanan
```
Order (sekali kirim):  submit ─► [pending_confirmation] ─► confirmed ─► item tampil di KDS
                                        └─► rejected
Item (per stasiun):    new ─► processing ─► ready ─► served
                        └──────┴──► cancelled (alasan + permission bila sudah masuk dapur, tercatat di log)
Bill:                  open ⇄ awaiting_payment ─► paid ─► refunded
                        ├─► void (sebelum dibayar)      └─► merged (digabung)
Meja:                  empty ─► occupied ─► awaiting_payment ─► needs_cleaning ─► empty
```

### API
Prefix `/api/v1`, format respons `{ data, message, meta }`, pesan error berbahasa Indonesia.
- **Publik** `/api/v1/public/...`: `store`, `menu`, `tables/{token}`, `tables/{token}/call`, `orders`, `track/{key}`, `track/{key}/pay`, `track/{key}/payments/{id}`, `webhooks/{midtrans|xendit}`
- **Staf** (Bearer token + permission per endpoint): `auth/*`, `dashboard/*`, `categories`, `menus`, `modifier-groups`, `tables`, `areas`, `table-calls`, `orders` (+ `feed`, confirm/reject), `order-items`, `kds`, `bills` (+ discount, voucher, points, customer, split, merge, move-table, payments, void, refund, receipt), `shifts`, `ingredients`, `units`, `purchases`, `stock/*`, `stock-opnames`, `suppliers`, `customers`, `vouchers`, `happy-hours`, `expenses`, `expense-categories`, `reports/{type}?format=json|xlsx|pdf`, `users`, `roles`, `permissions`, `settings`, `activity-logs`

Daftar lengkap: `php artisan route:list --path=api`.

### Keamanan pesanan publik
- Harga, opsi, diskon, pajak & total **selalu dihitung ulang di server**; field harga dari klien diabaikan (ada test-nya).
- Validasi opsi wajib/maksimum & kepemilikan opsi per menu, menu habis/nonaktif/di luar jam ditolak saat submit.
- Rate limit per IP, per meja, per path; honeypot `website`; batas qty & jumlah item (atur di Pengaturan).
- Token meja 48 karakter acak dan bisa diganti; kode pelacakan 32 karakter acak.
- Pembayaran memakai row lock (`SELECT ... FOR UPDATE`) sehingga tidak bisa dibayar ganda; webhook idempoten.

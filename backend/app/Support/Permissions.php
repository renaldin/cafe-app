<?php

namespace App\Support;

/** Daftar permission per modul (sumber tunggal untuk seeder & UI role). */
class Permissions
{
    public static function grouped(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'Lihat dashboard',
                'dashboard.finance' => 'Lihat angka omzet di dashboard',
            ],
            'Menu' => [
                'menu.view' => 'Lihat menu',
                'menu.manage' => 'Kelola menu, kategori & opsi',
                'menu.toggle' => 'Tandai menu habis/tersedia',
                'menu.import_export' => 'Import/export menu',
            ],
            'Meja' => [
                'table.view' => 'Lihat denah meja',
                'table.manage' => 'Kelola meja & area',
                'table.regenerate_qr' => 'Ganti token QR meja',
                'table.status' => 'Ubah status meja (bersihkan)',
            ],
            'Pesanan' => [
                'order.view' => 'Lihat pesanan',
                'order.create' => 'Buat pesanan manual / tambah item',
                'order.confirm' => 'Konfirmasi/tolak pesanan pelanggan',
                'order.cancel_item' => 'Hapus item yang sudah masuk dapur',
            ],
            'Dapur/Bar' => [
                'kds.view' => 'Lihat layar dapur/bar',
                'kds.update' => 'Ubah status pesanan di dapur/bar',
            ],
            'Kasir' => [
                'bill.view' => 'Lihat bill',
                'bill.manage' => 'Kelola bill (minta bill, split, gabung, pindah meja, voucher)',
                'bill.discount' => 'Beri diskon manual',
                'bill.void' => 'Void bill',
                'bill.refund' => 'Refund bill',
                'payment.process' => 'Proses pembayaran',
                'shift.manage' => 'Buka/tutup shift sendiri',
                'shift.view_all' => 'Lihat & tutup shift semua kasir',
            ],
            'Stok' => [
                'stock.view' => 'Lihat stok bahan',
                'stock.manage' => 'Kelola bahan, pembelian, penyesuaian, opname, waste',
                'recipe.manage' => 'Kelola resep menu',
                'supplier.manage' => 'Kelola supplier',
            ],
            'Pelanggan & Promo' => [
                'customer.view' => 'Lihat pelanggan',
                'customer.manage' => 'Kelola pelanggan & poin',
                'promo.manage' => 'Kelola voucher & happy hour',
            ],
            'Keuangan & Laporan' => [
                'expense.manage' => 'Catat biaya operasional',
                'report.view' => 'Lihat laporan operasional',
                'report.finance' => 'Lihat laporan keuangan (laba rugi, HPP)',
            ],
            'Administrasi' => [
                'user.manage' => 'Kelola staf',
                'role.manage' => 'Kelola role & permission',
                'setting.manage' => 'Ubah pengaturan cafe',
                'log.view' => 'Lihat log aktivitas',
            ],
        ];
    }

    public static function flat(): array
    {
        return array_merge(...array_values(self::grouped()));
    }

    /** Permission bawaan per role */
    public static function defaultRoles(): array
    {
        $all = array_keys(self::flat());

        return [
            'Owner' => $all,
            'Manajer' => array_values(array_diff($all, ['role.manage', 'setting.manage', 'report.finance'])),
            'Kasir' => [
                'dashboard.view', 'dashboard.finance', 'menu.view', 'menu.toggle', 'table.view', 'table.status',
                'order.view', 'order.create', 'order.confirm', 'bill.view', 'bill.manage', 'payment.process', 'shift.manage',
                'customer.view', 'customer.manage',
            ],
            'Pelayan' => [
                'dashboard.view', 'menu.view', 'table.view', 'table.status', 'order.view', 'order.create', 'order.confirm', 'bill.view',
            ],
            'Dapur' => ['kds.view', 'kds.update', 'menu.view', 'menu.toggle'],
            'Bar' => ['kds.view', 'kds.update', 'menu.view', 'menu.toggle'],
        ];
    }
}

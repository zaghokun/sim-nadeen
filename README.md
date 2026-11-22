# SIM Furniture Nadeen

Aplikasi web internal untuk pesanan furniture, estimasi HPP, pembayaran, buku kas, inventori, dan laporan pemilik. Stack yang dipilih adalah Laravel 13, PHP 8.4, Blade, Vite, dan MySQL 8.4 LTS lokal.

[Rancangan implementasi](docs/RANCANGAN-IMPLEMENTASI.md) · [Laporan perancangan sumber](Laporan%20Tahap%20Perancangan%20-%20UMKM%20Furniture%20Nadeen.docx)

## Status proyek

Tahap **T00 Rancangan** selesai pada 22 November 2026. Tahap **T01 Fondasi lokal** adalah tahap implementasi pertama. Seluruh package di bawah masih berstatus **Direncanakan**, karena instalasi untuk proyek dimulai pada fase implementasi.

## Pelacak tahapan

Ubah status menjadi **Selesai** setelah syarat di rancangan terpenuhi. Isi tanggal dan tautan ke bukti, misalnya file, commit, laporan uji, atau hasil rekonsiliasi.

| Status | Tahap | Capaian yang diperiksa | Bukti atau tanggal |
| --- | --- | --- | --- |
| Selesai | T00 Rancangan | Rancangan teknis, pemetaan laporan, arah Anti Slop, dan pelacak tersedia | [Rancangan](docs/RANCANGAN-IMPLEMENTASI.md) · 22 November 2026 |
| Direncanakan | T01 Fondasi lokal | Laravel berjalan, build aset dan migrasi MySQL lokal berhasil | |
| Direncanakan | T02 Akun, master, dan layout | Login, peran, master, kategori, dan layout berjalan | |
| Direncanakan | T03 Inventori dan lot awal | Lot, saldo, kondisi, riwayat mutasi, dan peringatan stok teruji | |
| Direncanakan | T04 Pesanan dan estimasi | Draf, HPP, konfirmasi, potong stok, dan nota teruji | |
| Direncanakan | T05 Pembelian, bayar, dan buku kas | Pembelian, DP, pelunasan, posting, pembalik, dan saldo teruji | |
| Direncanakan | T06 Produksi dan pengiriman | Alur produksi dan kirim, pemakaian bahan, HPP aktual, dan histori status teruji | |
| Direncanakan | T07 Laporan dan dashboard | Arus kas, laba rugi, laba per pesanan, inventori, dan konsentrasi cocok dengan data uji | |
| Direncanakan | T08 Migrasi dan backup | Impor percobaan, rekonsiliasi, retry, serta backup dan restore teruji | |
| Direncanakan | T09 Uji penerimaan dan pilot | Uji sistem, aksesibilitas, responsivitas, dan pilot tercatat | |

## Pelacak package dan alat

Ubah status menjadi **Terverifikasi** setelah versi terpasang dan fungsi terkait sudah diuji. Rekam versi aktual dari lockfile atau keluaran pemeriksaan runtime.

| Package atau alat | Tahap | Status | Versi terpasang | Bukti verifikasi |
| --- | --- | --- | --- | --- |
| PHP 8.4 dan Composer 2 | T01 | Direncanakan | | `php -v`, `composer --version` |
| MySQL Server 8.4 LTS dan CLI | T01 | Direncanakan | | Versi server, koneksi, dan migrasi |
| Node.js 24 LTS dan npm | T01 | Direncanakan | | `node -v`, `npm -v` |
| `laravel/laravel` | T01 | Direncanakan | | Versi scaffold dan halaman awal |
| `laravel/framework` | T01 | Direncanakan | | `php artisan --version` dan uji HTTP |
| `ext-pdo_mysql` | T01 | Direncanakan | | `composer check-platform-reqs` dan koneksi MySQL |
| `ext-bcmath` | T01 | Direncanakan | | `composer check-platform-reqs` dan uji operasi desimal |
| `vite` | T01 | Direncanakan | | `npm run build` |
| `laravel-vite-plugin` | T01 | Direncanakan | | Halaman Blade memuat aset hasil build |
| `laravel/pint` | T01 | Direncanakan | | `php vendor/bin/pint --test` |
| `phpunit/phpunit` | T01 | Direncanakan | | `php artisan test` |
| `phpoffice/phpspreadsheet` | T08 | Direncanakan | | Impor `dry-run` atas fixture Excel |
| Apache HTTP Server 2.4 | T09 | Direncanakan | | `httpd -t`, akses HTTPS, dan login dari host pilot |

## Keputusan yang perlu dicatat sebelum pilot

- [ ] Klasifikasi **Jual Stock Non-Rutin** untuk histori buku kas.
- [ ] Acuan upah, overhead, dan perlakuan biaya dalam laporan laba rugi.
- [ ] SKU produk, varian, jumlah, umur, dan nilai awal persediaan.
- [ ] Daftar staf dan peran, ketentuan bayar sebelum kirim, basis laporan, dan ambang peringatan.
- [ ] Periode histori, saldo awal cutover, serta rekonsiliasi hasil impor.

## Catatan verifikasi

| Tanggal | Tahap | Perubahan atau pemeriksaan | Hasil dan bukti |
| --- | --- | --- | --- |
| 22 November 2026 | T00 | Tinjau laporan, tabel, diagram, dan kontras palet rancangan | [Rancangan implementasi](docs/RANCANGAN-IMPLEMENTASI.md) |

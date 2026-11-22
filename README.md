# SIM Furniture Nadeen

Aplikasi web internal untuk pesanan furniture, estimasi HPP, pembayaran, buku kas, inventori, dan laporan pemilik. Stack yang dipilih adalah Laravel 13, PHP 8.4, Blade, Vite, dan MySQL 8.4 LTS lokal.

[Rancangan implementasi](docs/RANCANGAN-IMPLEMENTASI.md) · [Laporan perancangan sumber](docs/Laporan%20Tahap%20Perancangan%20-%20UMKM%20Furniture%20Nadeen.docx)

## Menjalankan proyek

Siapkan **PHP 8.4**, **Composer 2**, **Node.js 24** dengan **npm 11**, dan **MySQL Community Server 8.4**. Di Windows, gunakan PowerShell dari folder proyek. Jika memakai Laragon, pastikan terminal memilih versi PHP dan MySQL tersebut. Aktifkan `pdo_mysql` dan `bcmath` pada PHP CLI. `composer check-platform-reqs` memeriksa semua ekstensi yang dibutuhkan.

### Siapkan MySQL lokal

Jalankan MySQL lokal dengan akun `root` dan password kosong. Masuk melalui klien SQL atau jalankan `mysql --host=127.0.0.1 --user=root`, lalu buat kedua database. Jika port MySQL berbeda, gunakan port yang sama pada klien dan konfigurasi Laravel.

```sql
CREATE DATABASE IF NOT EXISTS sim_nadeen CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS sim_nadeen_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
```

### Pasang dependensi dan buka aplikasi

```powershell
composer install
composer check-platform-reqs
npm.cmd ci
Copy-Item .env.example .env
```

`.env.example` sudah berisi `DB_USERNAME=root` dan `DB_PASSWORD=`. Jika sudah punya `.env`, ubah kedua nilai tersebut di file yang ada. Sesuaikan `DB_PORT` jika MySQL memakai port lain.

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan nadeen:buat-admin
npm.cmd run build
php artisan serve
```

Buka [halaman aplikasi lokal](http://127.0.0.1:8000) dan masuk dengan akun admin yang baru dibuat. Perintah `nadeen:buat-admin` meminta nama lengkap, nama pengguna, dan kata sandi minimal 10 karakter. Buat akun staf dari menu **Akun** dan pilih peran **Produksi & Gudang**.

Untuk workspace yang sudah menjalankan T01, mulai dari `php artisan migrate --seed`. Seeder mengisi delapan kategori kas awal dan mempertahankan perubahan pada kategori yang sudah ada.

Admin bisa mencari, menambah, mengubah, dan menonaktifkan pelanggan, supplier, produk, standar HPP, serta kategori kas. Produksi dan gudang bisa membaca produk dan supplier aktif dengan kolom operasional. Standar HPP dibedakan berdasarkan produk dan tanggal mulai berlaku.

Untuk memeriksa status proses Laravel dalam format JSON, jalankan `curl.exe -H "Accept: application/json" http://127.0.0.1:8000/up`.

Saat mengubah CSS, jalankan `npm.cmd run dev` di terminal kedua agar Vite memuat perubahan. Pengguna macOS atau Linux dapat memakai `npm` sebagai ganti `npm.cmd` dan `cp` sebagai ganti `Copy-Item`.

### Jalankan pengujian

Salin `.env.testing.example` ke `.env.testing`, atau sesuaikan file pengujian yang sudah ada agar memakai `DB_USERNAME=root`, `DB_PASSWORD=`, dan `DB_DATABASE=sim_nadeen_test`. Jalankan perintah ini dari terminal baru.

```powershell
php artisan key:generate --env=testing
php artisan migrate --env=testing
npm.cmd run build
php vendor/bin/pint --test
php artisan test
```

Suite memeriksa halaman masuk, aset build, koneksi MySQL, login kedua peran, pembatasan akses, manajemen akun, dan validasi master. Migrasi dan transaksi pengujian memakai database `sim_nadeen_test`.

### Berbagi dan berkontribusi

Bagikan akses ke repositori privat atau salinan kode dari riwayat Git. Sertakan `composer.lock`, `package-lock.json`, dan dua file contoh lingkungan. Setiap anggota menyiapkan `.env`, `.env.testing`, kunci aplikasi, dan MySQL di komputernya. Dependensi lokal, file lingkungan aktual, dan hasil build sudah masuk `.gitignore`.

Gunakan branch per pekerjaan. Sebelum mengajukan perubahan, jalankan pemeriksaan format, build, dan pengujian di atas. Perbarui pelacak saat satu tahap lulus. Jika perubahan menyentuh struktur database, sertakan migrasinya agar anggota lain bisa menjalankan `php artisan migrate`.

## Status proyek

Tahap **T00 Rancangan** sampai **T02 Akun, master, dan layout** selesai. Tahap berikutnya adalah **T03 Inventori dan lot awal**.

## Pelacak tahapan

Ubah status menjadi **Selesai** setelah syarat di rancangan terpenuhi. Isi tanggal dan tautan ke bukti, misalnya file, commit, laporan uji, atau hasil rekonsiliasi.

| Status | Tahap | Capaian yang diperiksa | Bukti atau tanggal |
| --- | --- | --- | --- |
| Selesai | T00 Rancangan | Rancangan teknis, pemetaan laporan, arah Anti Slop, dan pelacak tersedia | [Rancangan](docs/RANCANGAN-IMPLEMENTASI.md) · 22 November 2026 |
| Selesai | T01 Fondasi lokal | Laravel berjalan, build aset dan migrasi MySQL lokal berhasil | [Bukti verifikasi](#catatan-verifikasi) · 22 November 2026 |
| Selesai | T02 Akun, master, dan layout | Login dua peran, pengelolaan akun dan master, kategori kas awal, serta layout responsif teruji | [Bukti verifikasi](#catatan-verifikasi) · 23 November 2026 |
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
| PHP 8.4 dan Composer 2 | T01 | Terverifikasi | 8.4.12 / 2.9.4 | `php -v`, `composer --version` |
| MySQL Server 8.4 LTS dan CLI | T01 | Terverifikasi | 8.4.3 | Koneksi TCP localhost dan migrasi pada dua database |
| Node.js 24 LTS dan npm | T01 | Terverifikasi | 24.19.0 / 11.17.0 | `node -v`, `npm -v`, dan `npm ci` |
| `laravel/laravel` | T01 | Terverifikasi | 13.0.0 | Scaffold yang dipakai untuk inisialisasi |
| `laravel/framework` | T01 | Terverifikasi | 13.43.0 | `php artisan --version` dan uji HTTP |
| `ext-pdo_mysql` | T01 | Terverifikasi | 8.4.12 | `composer check-platform-reqs` dan koneksi MySQL |
| `ext-bcmath` | T01 | Terverifikasi | 8.4.12 | `composer check-platform-reqs` dan uji operasi desimal |
| `vite` | T01 | Terverifikasi | 8.3.8 | `npm run build` |
| `laravel-vite-plugin` | T01 | Terverifikasi | 3.2.1 | Halaman Blade memuat aset hasil build |
| `laravel/pint` | T01 | Terverifikasi | 1.32.3 | `php vendor/bin/pint --test` |
| `phpunit/phpunit` | T01 | Terverifikasi | 12.5.45 | `php artisan test` |
| `nunomaduro/collision` | T01 | Terverifikasi | 8.9.6 | Integrasi dan keluaran pengujian Artisan |
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
| 22 November 2026 | T01 | Instalasi dari lockfile, pemeriksaan platform, Pint, dan build | Lulus `composer validate --strict`, `composer install`, `composer check-platform-reqs`, `npm ci`, `npm run build`, dan `php vendor/bin/pint --test` |
| 22 November 2026 | T01 | MySQL lokal dan HTTP | Migrasi awal pada `sim_nadeen` dan `sim_nadeen_test` berhasil di instans MySQL terisolasi. Kedua rute HTTP merespons 200. PHPUnit lulus 2 tes dan 14 asersi |
| 23 November 2026 | T02 | Build, migrasi, dan uji akses serta master | Build dan Pint lulus. PHPUnit lulus 9 tes dan 121 asersi pada MySQL terisolasi. Migrasi diterapkan ke kedua database lokal dan delapan kategori awal diisi pada database pengembangan |
| 23 November 2026 | T02 | Uji antarmuka Chrome | Login kedua peran, logout, respons 403, keyboard, input master, perubahan status, filter, dan paginasi diuji. Halaman masuk dan daftar produk diperiksa pada lebar 320, 375, 768, 1024, dan 1440 px |

### Pemeriksaan Anti Slop T02

- **Hard Gate lulus.** Lima lebar layar lulus tanpa overflow horizontal. Chrome menampilkan fokus keyboard, pesan login gagal, keadaan kosong, dan halaman akses dibatasi. Galat konsol yang tidak diharapkan nihil.
- **Purpose Gate lulus.** Hijau tua menandai navigasi dan tindakan utama. Nada kertas dan garis mendatar membantu memindai daftar. Rasio kontras pasangan teks utama yang diperiksa berada pada 7,33:1 atau lebih.
- **Liveliness lulus.** Wordmark Georgia menonjol di halaman masuk. Di area kerja, judul dan daftar menjadi fokus. Sidebar desktop menjaga tombol **Keluar** tetap terlihat dan tautan navigasi ponsel tampil di atas konten.
- **Craftsmanship lulus.** Kedua peran diuji di Chrome dan lewat HTTP. Tabel beralih ke pasangan label dan nilai di layar sempit. Tombol simpan, batal, ubah, cari, hapus filter, dan navigasi halaman menjalankan fungsi yang tertera.

### Pemeriksaan Anti Slop T01

- **Hard Gate lulus.** Chrome memuat halaman dan CSS pada lebar 320, 375, 768, 1024, dan 1440 px tanpa overflow horizontal atau galat konsol. Judul dan landmark utama tersedia dengan `lang="id"`.
- **Purpose Gate lulus.** Warna kertas menjadi latar, satu garis memisahkan judul dan status, serta hijau tua menandai fondasi berjalan dengan rasio kontras 7,95:1.
- **Liveliness lulus.** Judul aplikasi menjadi fokus tunggal, dengan jarak responsif dan garis buku kerja dari rancangan.
- **Craftsmanship lulus.** Halaman menampilkan status dari aplikasi yang berhasil merender Blade dan memakai sesi MySQL. Health check JSON memverifikasi proses Laravel.

# Impor Excel, Backup, dan Pilot SIM Nadeen

## Persiapan impor

Gunakan berkas asli sebagai rujukan dan susun salinan normalisasi di lokasi privat. Pakai format `.xlsx` dan tempel nilai hasil rumus. Isi tanggal dengan format `YYYY-MM-DD` atau tanggal asli Excel. Untuk nominal, gunakan angka tanpa pemisah ribuan dan maksimal dua angka desimal. Kuantitas bahan maksimal tiga angka desimal.

```powershell
php artisan nadeen:template-impor storage/app/private/histori.xlsx
php artisan nadeen:impor storage/app/private/histori.xlsx --dataset=histori-2026 --admin=NAMA_USER_ADMIN --dry-run
```

Isi satu `kunci` stabil per lembar untuk setiap identitas sumber. Referensi antarlembar berisi kunci tersebut. Gunakan nama dataset yang sama untuk pekerjaan yang saling menaut. Sel opsional boleh dikosongkan. Gunakan `0` untuk nilai boolean mati dan `1` untuk aktif.

| Lembar | Cara isi |
| --- | --- |
| `pelanggan`, `supplier` | Masukkan nama baku. Untuk alias yang sudah ada di SIM, gunakan `id_lokal` dari halaman **Ubah** untuk mengaitkan data ke entri tersebut. |
| `produk`, `bahan`, `hpp` | Isi kode master, kategori, stok minimum, dan satuan sesuai template. Pada HPP, rujuk kunci produk, isi tanggal berlaku, biaya bahan, upah, overhead, serta markup. Cocokkan nilai terlebih dahulu bila master dengan kode sama sudah ada. |
| `lot` | Gunakan `barang` atau `bahan` di kolom jenis. Rujuk kunci barang atau bahan lewat kolom item. Isi jumlah, kondisi `layak` atau `rusak`, dan alasan. Tanggal atau nilai yang belum diketahui boleh kosong. Pilih posisi awal sebelum transaksi yang akan diimpor agar pembelian dan alokasi tidak dihitung dua kali. |
| `pesanan`, `rincian` | Gunakan status `draf` untuk pesanan yang masih menunggu HPP atau harga. Gunakan `konfirmasi` jika data siap diposting. Rincian memakai sumber `stok` atau `khusus`, dengan satu hingga 20 baris per pesanan. Sertakan induk dan rincian dalam berkas yang sama. Lot barang stok harus sudah tersedia menurut tanggal pesanan. Penyelesaian produksi, HPP aktual khusus, dan pengiriman dicatat melalui aplikasi setelah data disepakati. |
| `pembelian` | Rujuk supplier, bahan, dan opsional pesanan terkonfirmasi. Isi tanggal, kuitansi, jumlah dan nominal yang benar-benar dibayar. Sistem membuat lot bahan dan jurnal keluar. |
| `pembayaran` | Rujuk pesanan terkonfirmasi dan isi tanggal, nominal, serta metode. Masukkan penerimaan sesuai urutan tanggal. Sistem menghitung status pembayaran dan jurnal masuk. |
| `kas` | Isi kode kategori, tanggal, arah `masuk` atau `keluar`, nominal dan keterangan. Untuk catatan yang sudah tercakup dalam lembar pembayaran atau pembelian, isi `sumber` dengan nama lembar dan `kunci_sumber` dengan kunci dokumen. Sistem memeriksa kesamaan arah, kategori, tanggal dan nominal sebelum menautkan jurnal. |

Contoh satu baris manual pada lembar `kas` dengan header bawaan.

| kunci | kategori | tanggal | arah | nominal | keterangan | masuk_hpp | sumber | kunci_sumber |
| --- | --- | --- | --- | ---: | --- | --- | --- | --- |
| kas-uji-01 | OPERASIONAL | 2026-11-23 | keluar | 40000 | Pengeluaran uji | 0 | | |

Perbaiki pengecualian yang disebutkan pada dry-run. Mode ini menguji transaksi dan membatalkan perubahan. Setelah hasil sesuai, jalankan backup dan ganti `--dry-run` dengan `--apply`. Penerapan menyimpan batch secara utuh. Impor ulang dengan dataset dan isi yang sama melewati baris yang sudah tercatat. Untuk penambahan, gunakan kunci baru. Koreksi transaksi yang telah diposting melalui dokumen sumber, supaya jejak koreksi dapat ditelusuri.

Rekonsiliasi jumlah baris per lembar, jumlah jurnal yang hanya ditautkan, saldo awal, saldo akhir, penerimaan per pesanan, pembelian dan nilai lot. Tahan transaksi historis yang memerlukan pembalik, pengiriman historis, atau data yang belum diketahui sampai pemilik mengesahkan pemetaannya.

## Backup dan restore MySQL

Jalankan dari PowerShell di folder proyek. Pilih nama file baru per backup dan simpan salinan di media privat terpisah. Simpan cadangan konfigurasi aplikasi, termasuk kunci aplikasi, pada penyimpanan akses terbatas. Penggunaan `--result-file` mempertahankan format dump pada Windows. Sesuaikan nama database dengan lingkungan yang diperiksa. [Dokumentasi mysqldump](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html)

```powershell
mysqldump.exe --host=127.0.0.1 --user=root --single-transaction --no-tablespaces --set-gtid-purged=OFF --result-file=storage/app/private/backup-nadeen.sql sim_nadeen
mysql.exe --host=127.0.0.1 --user=root --execute="CREATE DATABASE sim_nadeen_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
mysql.exe --host=127.0.0.1 --user=root --database=sim_nadeen_restore --execute="SOURCE storage/app/private/backup-nadeen.sql"
```

Pilih nama skema pemulihan kosong yang belum ada. Bandingkan hasil query berikut pada skema asal dan hasil pemulihan.

```sql
SELECT COUNT(*) AS pesanan FROM pesanan;
SELECT COUNT(*) AS jurnal,
       COALESCE(SUM(CASE WHEN arah = 'masuk' THEN nominal ELSE -nominal END), 0) AS saldo
FROM transaksi_kas;
SELECT COUNT(*) AS lot, COALESCE(SUM(jumlah_sisa), 0) AS jumlah, COALESCE(SUM(nilai_sisa), 0) AS nilai
FROM lot_stok;
```

Untuk pemakaian operasional, jalankan backup harian pada host pilot dan catat tanggal serta hasil pemeriksaan restore. Arahkan aplikasi salinan ke skema pemulihan ketika memeriksa login, aset, dan halaman transaksi.

## Pilot Windows melalui Apache HTTPS

1. Pada host kantor, siapkan Apache 2.4 dan PHP 8.4. Contoh memakai PHP Non Thread Safe lewat FastCGI agar sesuai dengan Laragon yang dipakai untuk pengembangan. Aktifkan modul `mod_ssl`, `mod_proxy`, `mod_proxy_fcgi`, `mod_rewrite`, dan `mod_dir`. Aktifkan `Listen 443` pada konfigurasi Apache jika belum ada. [Apache FastCGI](https://httpd.apache.org/docs/2.4/mod/mod_proxy_fcgi.html) · [PHP pada Apache Windows](https://www.php.net/manual/en/install.windows.apache2.php)
2. Salin [contoh virtual host](../deploy/apache/https-vhost.conf.example) ke konfigurasi Apache yang di-include. Sesuaikan root proyek, nama host, sertifikat, dan kunci. Pakai sertifikat yang dipercaya perangkat staf. Document root diarahkan ke `public/`.
3. Jalankan PHP FastCGI pada host pilot dengan `php-cgi.exe -b 127.0.0.1:9007` dan jalankan Apache setelah `httpd.exe -t` lulus. Atur keduanya sebagai layanan terkelola pada host pilot. FastCGI dan MySQL mendengarkan hanya di loopback. Akses HTTPS dibatasi ke jaringan kantor dari host tersebut.
4. Isi `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://NAMA_HOST_PILOT`, dan `SESSION_SECURE_COOKIE=true`. Tetapkan `NADEEN_SHIPPING_PAYMENT` sesuai keputusan pemilik. Nilai yang didukung adalah `bebas`, `dp`, dan `lunas`. Nilai default pengembangan adalah `bebas`.
5. Jalankan `composer install --no-dev --optimize-autoloader`, `npm.cmd ci`, `npm.cmd run build`, dan `php artisan migrate --seed --force`. Jalankan `php artisan config:cache` setelah konfigurasi dipastikan tepat. Periksa `/up`, CSS, login, 403 pada peran terbatas, sesi, dan cetak dari perangkat staf.
6. Selesaikan pemeriksaan pilot pada [checklist T09](UJI-MANUAL.md#t09-uji-penerimaan-tampilan-dan-pilot). Gunakan data awal yang pemilik sudah periksa, pastikan hasil backup dan restore sama, lalu rekam siapa yang menyetujui dan kapan sistem mulai digunakan.

Uji localhost berhasil menggunakan Apache 2.4.66 dan sertifikat uji. Paket Laragon di komputer pengembangan mencatat `AH01882` karena versi pustaka OpenSSL yang dimuat berbeda dari versi yang dipakai saat membangun `mod_ssl`. Pilih distribusi Apache dan OpenSSL yang sepadan untuk host pilot dan periksa log saat memulai Apache.

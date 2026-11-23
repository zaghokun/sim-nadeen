# Checklist Uji Manual SIM Furniture Nadeen T01 hingga T09

Jalankan dari atas untuk pengujian pertama. Gunakan database pengembangan kosong, misalnya `sim_nadeen_manual`, supaya angka rekonsiliasi pada contoh dapat dibandingkan langsung. Gunakan dua sesi browser, jendela biasa untuk Pemilik/Admin dan Incognito untuk Produksi & Gudang. Catat setiap hasil **Lulus**, **Gagal**, atau **Tertahan** beserta tanggal, browser, peran, URL, dan hasil aktual.

## T01 Fondasi lokal

- [ ] Aktifkan MySQL 8.4 lokal. Buat database pengembangan kosong dan database tes `sim_nadeen_test` memakai langkah di [README](../README.md#siapkan-mysql-lokal). Gunakan `DB_USERNAME=root`, `DB_PASSWORD=`, dan `DB_HOST=127.0.0.1`. Arahkan `DB_DATABASE` ke database pengembangan kosong.
- [ ] Periksa `php -v`, `composer --version`, `node -v`, dan `mysql --version`. PHP 8.4 harus memuat `pdo_mysql`, `bcmath`, `gd`, dan `zip`. Jalankan `composer install`, `composer check-platform-reqs`, `npm.cmd ci`, dan `npm.cmd run build`. Perintah selesai tanpa kegagalan.
- [ ] Salin `.env.example` bila belum ada dan jalankan `php artisan key:generate` sekali pada konfigurasi baru. Lanjutkan dengan `php artisan migrate --seed`, `php artisan migrate:status`, dan `php artisan serve`. Semua migrasi berstatus **Ran**. Halaman masuk memuat CSS saat dibuka dari alamat yang dilaporkan terminal.
- [ ] Jalankan `curl.exe -H "Accept: application/json" http://127.0.0.1:8000/up`. Health check proses menjawab `{"status":"up"}`. Buka halaman masuk dan lakukan login pada T02 untuk membuktikan sesi MySQL juga berjalan.
- [ ] Salin `.env.testing.example` bila akan menjalankan tes otomatis. Periksa bahwa nama database tes adalah `sim_nadeen_test`. Jalankan `php artisan key:generate --env=testing` pada konfigurasi tes baru dan `php artisan migrate --env=testing`.

## T02 Akun dan master

- [ ] Jalankan `php artisan nadeen:buat-admin`. Buka `/master/produk` dari jendela yang belum login untuk memeriksa pengalihan ke halaman masuk. Coba kata sandi salah, lalu masuk dengan kata sandi benar. Halaman **Produk** terbuka dan **Master data** memuat menu admin.
- [ ] Dari **Master data → Akun**, buat akun dengan peran **Produksi & Gudang**. Konfirmasi kata sandi yang berbeda harus ditolak. Nama pengguna duplikat tetap ditolak ketika kapitalisasinya berbeda. Coba nonaktifkan akun admin yang sedang dipakai. Sistem mempertahankan akses admin tersebut.
- [ ] Buat pelanggan `Pelanggan Uji A` dan `Pelanggan Uji B`, serta supplier `Supplier Uji` dengan jenis bahan `Kayu` dan kontak contoh. Cari nama, buka **Ubah**, ganti satu nilai, lalu simpan. Klik **Batal** pada perubahan lain, lalu periksa data tersimpan.
- [ ] Buat produk `UJI-KURSI` dengan nama **Kursi Uji**, kategori **Kursi**, harga standar `250000`, dan stok minimum `2`. Cari berdasarkan kode dan nama. Coba membuat kode `uji-kursi` lagi untuk melihat penolakan duplikat. Ubah status ke nonaktif, uji filter status, lalu aktifkan kembali.
- [ ] Buat standar HPP untuk `UJI-KURSI` dengan tanggal berlaku hari ini atau sebelumnya, bahan `100000`, upah `20000`, overhead `10000`, markup `20`. Total harus **Rp 130.000**. Produk dan tanggal yang sama ditolak. Versi kedua dengan tanggal mendatang dapat disimpan dan belum dipakai pada pesanan hari ini.
- [ ] Buka **Kategori Kas** dan periksa delapan kategori awal pada database baru. Tambah kategori uji, cari, ubah label dan status, lalu simpan. Kode kategori yang sudah tersimpan hanya dapat dibaca. Pengulangan `php artisan db:seed` mempertahankan perubahan label.
- [ ] Masuk di Incognito sebagai staf. Produk dan supplier aktif dapat dibaca. Halaman master Produk tidak menampilkan harga standar dan Supplier tidak menampilkan kontak. Buka `/master/hpp`, `/master/pelanggan`, `/master/produk/tambah`, dan `/akun` lewat alamat langsung. Masing-masing memberi **403**.
- [ ] Dengan kedua sesi aktif, ubah kata sandi staf dari Admin. Muat ulang tab staf, pastikan **Sesi berakhir**, lalu verifikasi kata sandi lama ditolak dan kata sandi baru berhasil. Ulangi dengan menonaktifkan akun staf. Aktifkan kembali untuk tahap berikutnya. Klik **Keluar** dan buktikan halaman yang dilindungi meminta login kembali.
- [ ] Coba enam percobaan login gagal dalam satu menit dengan nama pengguna uji yang tidak terdaftar. Percobaan keenam menampilkan waktu tunggu. Gunakan nama pengguna lain untuk melanjutkan verifikasi.

## T03 Inventori dan lot awal

- [ ] Sebagai Admin, buat `UJI-KAYU` dan `UJI-KAIN` dari **Inventori → Bahan baku → Tambah bahan**. Gunakan satuan `m`, kategori masing-masing, stok minimum Kayu `2.5` dan Kain `2`. Pada awalnya keduanya tampil dalam filter **Stok minimum**.
- [ ] Sebagai staf, buka **Inventori → Bahan baku → UJI-KAIN → Catat stok masuk**. Catat jumlah `2.5`, kondisi **Layak**, alasan `Opname uji`, dan kosongkan tanggal. Lot tampil dengan tanggal **Belum diketahui**. Stok layak bertambah 2,5 m dan jumlah siap dialokasikan tetap nol.
- [ ] Coba keluarkan `1` m dari lot yang belum disahkan. Permintaan ditolak. Sebagai Admin, buka **Sahkan nilai** dan isi `50000`. Sebagai staf, pastikan jumlah siap dialokasikan menjadi 2,5 m tanpa kolom valuasi. Coba ubah kondisi ke **Rusak** lalu kembali ke **Layak**. Stok rusak dikecualikan dari stok layak dan tercatat pada riwayat mutasi.
- [ ] Catat lot lain untuk Kain dengan tanggal tepat 180 hari sebelum tanggal WIB hari ini, lalu satu lot yang lebih tua sehari. Filter **Lot lama** hanya memasukkan item setelah ada lot layak berumur lebih dari 180 hari. Lot dengan tanggal tidak diketahui tampil dengan penanda tersendiri.
- [ ] Buka **Inventori → Barang jadi → UJI-KURSI**. Buat dua lot layak dengan nilai disahkan. Lot pertama masuk lebih awal, jumlah `2` dengan nilai `240000`. Lot kedua jumlah `3` dengan nilai `450000`. Gunakan tanggal lampau atau hari ini. Total menjadi 5 unit dan **Rp 690.000**.
- [ ] Coba menerima `0.5` unit Kursi dan mengeluarkan 6 unit pada formulir penyesuaian. Kedua tindakan ditolak, jumlah dan nilai lot tetap sama. Gunakan lot Kain untuk mencoba pengeluaran pecahan yang sah. Periksa perubahan positif dan negatif pada **Riwayat mutasi**.

## T04 Pesanan dan estimasi

- [ ] Sebagai Admin, buka **Pesanan → Tambah pesanan**, pilih Pelanggan Uji A, lalu pilih `UJI-KURSI`. HPP estimasi **Rp 130.000** dan harga saran **Rp 156.000** muncul. Tombol **Pakai harga saran** mengisi harga. Ubah harga ke `250000`, sumber **Stok**, jumlah `3`.
- [ ] Klik **Tambah baris**, pilih produk, periksa estimasi untuk baris kedua dan ringkasan, lalu hapus baris kedua. Simpan sebagai **Pesanan A** dengan satu baris. Nomor berbentuk `ORD-tahun-nomor` muncul dan stok masih 5 unit.
- [ ] Buka **Ubah draf** dan simpan tanpa mengganti identitas. Nomor pesanan tetap. Buka **Nota estimasi** untuk memeriksa pelanggan, SKU, jumlah, harga, dan total **Rp 750.000**.
- [ ] Buat draf terpisah dengan harga kosong atau produk aktif yang belum punya HPP. Coba konfirmasi. Sistem memberi alasan penolakan dan pesanan tetap draf. Batalkan draf uji ini.
- [ ] Konfirmasi Pesanan A. Tiga unit teralokasi dari lot tertua dahulu. Lot pertama tersisa nol, lot kedua tersisa 2 unit senilai **Rp 300.000**. HPP aktual pesanan **Rp 390.000** dan produksi berstatus **Selesai**. Nota berubah menjadi **Nota tagihan**. Ulangi permintaan konfirmasi dari tab yang masih menampilkan formulir lama. Stok hanya berkurang sekali.
- [ ] Buat **Pesanan B** untuk Pelanggan Uji B dengan satu unit `UJI-KURSI`, sumber **Pesanan khusus**, harga `187500`. Konfirmasi. Barang jadi tidak berkurang dan status produksi **Antri**. Staf dapat melihat pesanan terkonfirmasi, sementara tautan draf tetap menghasilkan **403**.
- [ ] Buat draf terpisah untuk stok 3 unit ketika saldo tinggal 2. Konfirmasi harus gagal. Periksa saldo tetap 2 dan tidak ada mutasi alokasi untuk draf tersebut. Uji persaingan dengan dua draf masing-masing 2 unit pada salinan database. Konfirmasi dari dua tab hampir bersamaan. Hanya satu berhasil, saldo akhir nol.
- [ ] Pada salinan database atau pesanan stok tambahan, batalkan pesanan terkonfirmasi sebelum kirim. Sistem meminta konfirmasi barang kembali, dan menolak bila masih ada pembayaran bersih. Setelah syarat terpenuhi, riwayat pembalik mengembalikan jumlah dan nilai lot.

## T05 Pembelian, pembayaran, dan buku kas

Angka rekonsiliasi T07 memakai transaksi utama berikut. Selesaikan pencatatan pembalik di database salinan atau catat efeknya pada total masuk dan keluar.

- [ ] Di **Buku Kas → Catat transaksi**, catat **Saldo Awal**, arah **Masuk**, nominal `1000000`. Lalu catat **Operasional**, arah **Keluar**, nominal `40000`, keterangan `Beban uji`. Biarkan penanda HPP kosong. Coba kategori Operasional dengan arah Masuk untuk melihat penolakan.
- [ ] Pada Pesanan A, catat DP `250000`. Status menjadi **DP**, sisa **Rp 500.000**, dan tercipta satu kas masuk. Coba `500001` dan pastikan ditolak. Catat `500000` untuk pelunasan. Status menjadi **Lunas** dan bukti pembayaran dapat dibuka dari pesanan maupun Buku Kas.
- [ ] Pada Pesanan B, catat `100000` lalu `87500`. Pastikan sisa tagihan nol dan ada tepat dua mutasi kas masuk untuk B. Klik ganda **Simpan** atau kirim ulang formulir yang sama dari tab lama untuk memeriksa bahwa entri tidak terduplikasi.
- [ ] Sebagai staf, buka **Pembelian → Catat pembelian**. Pilih Supplier Uji, `UJI-KAYU`, Pesanan B, referensi `KW-UJI-01`, jumlah `5`, nominal `100000`, metode **Tunai**. Periksa lot bahan bertambah 5 m dan Admin melihat tepat satu kas keluar kategori **Bahan Baku**.
- [ ] Filter Buku Kas berdasarkan kategori dan tanggal. Saldo berjalan tiap baris tetap mencakup transaksi lain sebelumnya. Staf menerima **403** saat membuka `/buku-kas` maupun URL bukti pembayaran langsung.
- [ ] Pada database salinan, buka **Balik pembayaran** untuk satu penerimaan. Isi alasan. Status bayar dihitung ulang, kas berarah berlawanan muncul, dan dokumen asal tetap dapat dibuka. Lakukan uji serupa untuk **Balik jurnal** dan pembelian dengan lot utuh. Pembelian yang lotnya telah dipakai sebagian harus ditolak.

## T06 Produksi dan pengiriman

- [ ] Di sesi staf, buka Pesanan B. Pesanan yang belum siap tidak menawarkan tindakan kirim. Pada baris Barang, klik **Mulai pengerjaan**, lalu **Catat pemakaian**. Pilih `UJI-KAYU` dan masukkan `2.5` m.
- [ ] Periksa riwayat pemakaian dan lot kayu. Saldo kayu tersisa 2,5 m, tepat pada ambang minimum. Nilai sisa yang dilihat Admin **Rp 50.000**. Coba pemakaian tambahan yang melebihi saldo dan pastikan mutasi tidak bertambah.
- [ ] Staf menekan **Tandai siap**. Sebagai Admin, pilih **Sahkan HPP aktual** dan masukkan upah `60000`, overhead `15000`. HPP aktual seluruh baris **Rp 125.000**, terdiri atas bahan Rp 50.000, upah Rp 60.000, overhead Rp 15.000. Staf tidak memiliki akses mengesahkan HPP.
- [ ] Untuk Pesanan A dan B, klik **Tandai dikirim** lalu **Selesaikan pengiriman**. Di riwayat status, cocokkan urutan, aktor, dan timestamp WIB. Status pembayaran tetap sesuai transaksi pembayarannya.
- [ ] Pada salinan, coba pengiriman dengan konfigurasi `NADEEN_SHIPPING_PAYMENT=lunas` ketika pesanan siap masih DP. Pastikan sistem menolak. Lunasi, lalu kirim. Jalankan `php artisan config:clear` dan nyalakan ulang server setelah mengubah konfigurasi lokal.
- [ ] Untuk skenario pembalik pemakaian, gunakan pesanan tambahan sebelum kirim dan sebelum HPP disahkan. Admin membuka **Balik pemakaian**, mengisi alasan, lalu memeriksa bahwa kuantitas dan nilai kembali ke lot sumber.

## T07 Laporan dan dasbor

Gunakan bulan yang sama untuk dua pesanan utama dan seluruh posting pada T05. Angka berikut mengasumsikan database kosong sebelum pengujian dan skenario pembalik dijalankan terpisah.

| Layar | Hasil yang diharapkan |
| --- | --- |
| **Laporan → Arus kas** | Kas masuk **Rp 1.937.500**, termasuk saldo awal yang diposting Rp 1.000.000. Kas keluar **Rp 140.000**. Saldo akhir **Rp 1.797.500** dan arus operasional **Rp 797.500**. |
| **Laporan → Laba per pesanan** | A memiliki omzet Rp 750.000, HPP Rp 390.000, laba Rp 360.000, dan margin 48%. B memiliki omzet Rp 187.500, HPP Rp 125.000, laba Rp 62.500, dan margin 33,33%. |
| **Laporan → Laba rugi** | Omzet selesai kirim **Rp 937.500**, HPP **Rp 515.000**, beban **Rp 40.000**, hasil **Rp 382.500**. Belanja persediaan tidak dikurangkan lagi sebagai beban. |
| **Dasbor** | A berkontribusi **80%**, B **20%**. Ambang 80 tidak memicu peringatan untuk A. Ubah ke 79,99 agar peringatan muncul. |

- [ ] Uji filter periode, pelanggan, kode, kesiapan HPP, dan kategori. Cek tautan dari daftar laba per pesanan ke dokumen serta tautan dari Arus kas ke jurnal. Bulan kosong menampilkan keadaan kosong.
- [ ] Uji laporan ketika pesanan khusus sudah selesai kirim dan aktual belum disahkan. Laba rugi bertanda **Hasil sementara**. Setelah pengesahan, cakupan HPP meningkat. Uji kategori nonrutin secara terpisah dan cocokkan arus nonrutin pada laporan.
- [ ] Di akun staf, buka `/dashboard` dan `/laporan` langsung. Kedua halaman harus memberi **403**. Inventori tetap tersedia tanpa kolom nilai.

## T08 Impor Excel dan pemulihan

- [ ] Aktifkan ZIP PHP, lalu buat file baru dengan `php artisan nadeen:template-impor storage/app/private/impor-uji.xlsx`. Buka Excel dan cek nama lembar serta header. Gunakan database salinan, akun admin aktif, dan dataset terpisah. Format dan contoh isi ada di [panduan impor dan pilot](OPERASIONAL-DAN-PILOT.md).
- [ ] Isi satu baris pelanggan, produk, atau kas sederhana. Jalankan `php artisan nadeen:impor storage/app/private/impor-uji.xlsx --dataset=uji-t08 --admin=NAMA_USER_ADMIN --dry-run`. Ringkasan menampilkan baris baru. Periksa di browser bahwa perubahan masih belum ada.
- [ ] Jalankan ulang dengan `--apply`. Data yang dilaporkan harus muncul. Ulangi berkas dengan dataset yang sama. Baris berpindah ke kolom **Sudah dipetakan**, tanpa saldo atau dokumen ganda. Ubah isi baris lama tanpa mengganti kunci dan pastikan perubahan ditahan.
- [ ] Pada dataset terpisah, sisakan tanggal kas kosong. Jalankan `--dry-run` dan pastikan lembar serta nomor baris disebutkan. Jalankan `--apply` pada salinan untuk membuktikan bahwa batch ditahan dan baris operasional valid dari batch yang sama tidak tersimpan.
- [ ] Untuk rekap kas historis bertaut, jalankan kasus di mana lembar `pembayaran` dan `kas` memuat satu penerimaan yang sama. Isi kolom `sumber=pembayaran` dan `kunci_sumber` dari lembar pembayaran. Jurnal hanya tercatat satu kali. Referensi atau nominal yang berbeda harus menahan batch.
- [ ] Lakukan backup dan restore mengikuti panduan operasional. Pastikan nama skema tujuan berbeda. Cocokkan jumlah pesanan, jumlah jurnal, saldo kas, dan sisa stok sebelum dan setelah pemulihan.

## T09 Uji penerimaan, tampilan, dan pilot

- [ ] Jalankan `composer validate --strict`, `composer check-platform-reqs`, `npm.cmd ci`, `npm.cmd run build`, `php vendor/bin/pint --test`, dan `php artisan test`. Catat hasil dan versi runtime.
- [ ] Ulangi satu alur barang stok dan satu alur pesanan khusus dari masuk hingga laporan. Pada masing-masing, cek dokumen, akun, tanggal efektif WIB, nilai transaksi, status, dan mutasi terkait.
- [ ] Buka halaman masuk, daftar dan editor pesanan, detail lot, buku kas, serta laporan pada lebar **320, 375, 768, 1024, dan 1440 px**. Gunakan zoom 200%. Pastikan tidak ada geser halaman horizontal, fokus keyboard terlihat, nilai tetap terbaca, dan daftar kosong mempunyai pesan yang jelas.
- [ ] Dari nota estimasi, tagihan, dan bukti pembayaran, klik **Cetak atau simpan PDF**. Pratinjau menampilkan nomor dan nominal benar. Tombol navigasi hilang dari hasil cetak. Periksa juga dokumen dengan banyak baris dan format kertas yang akan dipakai.
- [ ] Saat menguji, periksa Chrome DevTools Console. Kegagalan sengaja seperti **403** sesuai skenario. Catat galat JavaScript, port, URL, browser dan langkah pengulangan bila ditemukan.
- [ ] Pada host pilot, ikuti konfigurasi Apache HTTPS di panduan operasional. Uji `/up`, login kedua peran, aset Vite, URL yang dilindungi, dan akses dari perangkat staf dengan sertifikat yang dipercaya.
- [ ] Setelah pemilik menyetujui cakupan histori, klasifikasi nonrutin, HPP awal, opname, dan aturan pembayaran sebelum kirim, jalankan dry-run data pilot, rekonsiliasi, backup, dan uji satu alur dengan data yang disetujui. Catat penanggung jawab dan waktu persetujuan.

Gunakan format berikut untuk tindak lanjut.

| Tahap dan kasus | Lingkungan | Harapan | Hasil aktual | Hasil dan bukti |
| --- | --- | --- | --- | --- |
| T04 · Konfirmasi stok | Chrome · Admin · database manual | Lot tertua habis dan sisa 2 unit | Isi hasil pengamatan | Lulus, Gagal, atau Tertahan |

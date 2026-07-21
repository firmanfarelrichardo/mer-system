# Panduan Pengujian

## Prinsip

Uji perilaku yang paling berisiko: pembatasan tenant/peran, privasi laporan, perubahan status, audit/notifikasi, serta kesiapan deploy. Gunakan akun dan data sintetis; jangan menguji dengan data pasien produksi.

## Perintah dasar

```bash
# Unit/feature test Laravel
composer test

# Analisis statis PHP
./vendor/bin/phpstan analyse

# Build aset frontend
npm run build

# Validasi syntax/hasil interpolasi Compose tanpa menjalankan stack
docker compose -f deployment/staging/docker-compose.yml config
docker compose -f deployment/production/docker-compose.yml config
```

Jika memakai Docker lokal yang sudah berjalan, jalankan artisan dalam container aplikasi yang benar untuk lingkungan tersebut. Hindari `migrate:fresh` pada data yang perlu dipertahankan.

## Matriks skenario minimum

| Area | Skenario | Hasil yang diharapkan |
| --- | --- | --- |
| Login | Kredensial valid/tidak valid, limit percobaan, akun nonaktif | Hanya pengguna valid/aktif yang masuk; limit diterapkan. |
| Force password | Flag `wajib_ganti_sandi` aktif | Akses fitur terproteksi dialihkan ke ubah sandi sampai selesai. |
| Tenant | Dua tenant sintetis dan satu laporan masing-masing | Pengguna tenant A tidak dapat melihat/bertindak atas laporan tenant B. |
| Nakes | Buat draf, auto-save, submit | Draf milik sendiri, tidak notifikasi; submit menjadi `kasus_baru` dan memiliki nomor `INC-...`. |
| Kepala Ruangan | Unit sesuai/tidak sesuai/belum ditugaskan | Hanya melihat unit sendiri; tanpa unit melihat nol data. |
| Komite/Direktur | Tindak lanjut, eskalasi, solusi | Komite dapat eskalasi; Direktur hanya solusi ketika eskalasi aktif dan tidak mengubah status umum. |
| Status | Karu selesai lalu Komite menindaklanjuti, dan sebaliknya | Penyelesaian independen per peran sesuai policy. |
| Notifikasi | Kasus baru, investigasi Sentinel, tindak lanjut, selesai | Penerima sesuai listener; draf tidak menghasilkan notifikasi eksternal. |
| Audit | Create/update draf, submit, tindak lanjut | Audit tercatat tanpa spam pada update auto-save. |
| PDF/ekspor | Laporan dan rentang rekap sah/tidak sah | Hanya peran berhak dapat keluaran; data/rentang tervalidasi. |

## Pengujian manual UI

1. Uji lebar layar desktop dan mobile untuk login, form laporan multi-langkah, daftar/detail, serta tindakan laporan.
2. Periksa indikator *auto-save*, navigasi langkah, pesan validasi, dan error jaringan tanpa kehilangan input.
3. Uji perubahan peran dual-role/Peneliti: menu, dasbor, scope data, dan tombol tindakan harus segera mencerminkan peran aktif.
4. Periksa bahwa data pasien tidak muncul pada halaman, notifikasi, atau ekspor yang tidak berhak.

## Pengujian staging/deploy

1. Validasi konfigurasi Compose dan variabel non-rahasia.
2. Naikkan stack staging, periksa healthcheck `app`, `db`, dan `redis`, lalu akses melalui SSH tunnel.
3. Jalankan migration normal dan seeder yang memang disetujui untuk staging.
4. Uji login, satu alur laporan, satu PDF, notifikasi in-app, dan log Laravel/Loki.
5. Untuk production, ikuti runbook deploy/backup resmi; lakukan backup serta rencana rollback sebelum perubahan skema.

## Pelaporan hasil test

Sertakan commit yang diuji, environment, perintah/skenario, hasil, dan blocker. Pisahkan kegagalan lingkungan (misalnya Docker belum berjalan) dari kegagalan aplikasi. Jangan menempelkan kredensial, header sesi, atau data pasien ke hasil test.

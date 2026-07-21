# Instruksi Sistem untuk AI Agent di MER System

## Peran agent

Bertindak sebagai rekan rekayasa perangkat lunak yang berhati-hati untuk sistem pelaporan insiden medis. Utamakan keselamatan data, privasi pasien, akurasi alur bisnis, dan perubahan kecil yang dapat diverifikasi.

## Urutan kerja wajib

1. Baca `PRD.md` dan dokumen `context/` yang relevan sebelum mengubah perilaku. Mulai dari `context/README.md` untuk navigasi.
2. Periksa `git status --short` serta file terkait sebelum menyunting; jangan menghapus perubahan pengguna yang sudah ada.
3. Cari implementasi yang ada dengan `rg` sebelum membuat pola/fitur baru.
4. Buat perubahan minimal yang memenuhi tujuan; gunakan migration untuk perubahan database.
5. Jalankan verifikasi proporsional dan nyatakan secara jujur apa yang belum dapat diuji.
6. Perbarui `context/03-management/current-progress.md`, `context/03-management/decisions-log.md`, backlog, dan PRD jika perubahan mengubah fakta yang didokumentasikan.
7. Perbarui `context/03-management/changelog.md` dengan tanggal dan waktu WIB aktual setiap kali file di folder `context/` ditambah, diubah secara substansial, dipindahkan, atau dihapus.

## Navigasi cepat dokumen context

| Kategori | Lokasi | Isi |
|----------|--------|-----|
| Informasi project | `context/01-project/` | Arsitektur, skema DB, alur fitur, tech stack |
| Panduan development | `context/02-development/` | Instruksi ini, code review, testing, konvensi kode |
| Manajemen project | `context/03-management/` | Progress, backlog, keputusan, changelog |
| Output agent | `context/04-agent-output/` | Hasil sesi AI agent |

Lihat `context/README.md` untuk peta lengkap dan cross-reference ke source code.

## Aturan domain yang tidak boleh dilanggar

- Semua data laporan harus dibatasi tenant. Jangan menulis query baru yang mengabaikan `tenant_id`.
- Pertahankan otorisasi server-side menggunakan policy dan scope. Menyembunyikan tombol bukan kontrol akses.
- Nilai status resmi: `DRAF`, `kasus_baru`, `investigasi`, `tindak_lanjut`, `selesai`.
- Draf bersifat privat milik pelapor dan tidak memicu notifikasi pihak lain.
- Direktur tidak mengubah status umum; solusi Direktur hanya untuk laporan yang dieskalasi Komite.
- Role switching bersifat session-aware. Gunakan helper model (`memilikiPeran`, `peranAktif`) alih-alih mengasumsikan peran database pertama.
- Perubahan notifikasi harus meninjau event, listener, policy, UI, dan pengujian bersama-sama.

## Keamanan dan privasi

- Jangan membaca, mencetak, menyimpan, atau mengirim ulang isi `.env`, kredensial, token, dump database, dan data pasien nyata kecuali benar-benar diperlukan serta diizinkan.
- Jangan memasukkan nama pasien, nomor rekam medis, kronologi, atau data produksi ke test/seed/dokumentasi.
- Jangan menonaktifkan autentikasi, CSRF, validasi, policy, audit, TLS, atau pembatasan port demi "memudahkan" pekerjaan.
- Masking IP/user agent harus dipertahankan; hanya Admin yang dikonfigurasi boleh melihat nilai lengkap.

## Praktik implementasi

- PHP: pertahankan `declare(strict_types=1)` pada file aplikasi yang menggunakannya, type hint, DTO, service, dan repository yang ada.
- Database: gunakan transaction untuk mutasi entitas laporan yang saling bergantung. Jangan edit skema lewat database produksi langsung.
- Frontend: server-side validation adalah sumber kebenaran; Alpine/Vite/Tailwind hanya pelengkap UI.
- Dokumen: gunakan Bahasa Indonesia yang spesifik, bedakan fakta terverifikasi dari asumsi, dan jangan menyalin rahasia.
- Konvensi: lihat `context/02-development/coding-conventions.md` untuk detail penamaan dan pattern.

## Batas tindakan berisiko

Memerlukan persetujuan eksplisit pengguna sebelum menjalankan tindakan yang dapat kehilangan data atau mengubah lingkungan bersama, termasuk `migrate:fresh`, `db:wipe`, reset database, `docker compose down -v`, penghapusan volume, deployment production, dan perubahan kredensial/infrastruktur eksternal.

## Perintah pemeriksaan yang dianjurkan

```bash
composer test
./vendor/bin/phpstan analyse
npm run build
docker compose -f deployment/staging/docker-compose.yml config
docker compose -f deployment/production/docker-compose.yml config
```

Sesuaikan dengan environment yang tersedia. Jangan mengklaim lulus bila perintah tidak dijalankan atau gagal.

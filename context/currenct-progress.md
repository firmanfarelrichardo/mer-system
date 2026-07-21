# Progres Saat Ini

> Catatan: nama file sengaja mengikuti struktur yang diminta (`currenct-progress.md`). Perbarui dokumen ini pada akhir pekerjaan besar agar agent berikutnya memiliki titik mulai yang akurat.

## Snapshot repository

- Branch/commit yang terdeteksi saat dokumen dibuat: `abdce2f` — `fix dockerfile healthcheck`.
- Working tree terdeteksi bersih sebelum folder `context/` dan `PRD.md` ditambahkan.
- Produk telah memiliki fitur inti pelaporan, RBAC, draf/*auto-save*, notifikasi, audit log, PDF, statistik, Docker staging/production, dan dokumentasi operasional 01–07.

## Yang baru didokumentasikan

| Artefak | Status | Fungsi |
| --- | --- | --- |
| `PRD.md` | Selesai | Kebutuhan produk dan kriteria penerimaan. |
| `context/architecture.md` | Selesai | Batas komponen dan arsitektur. |
| `context/*.md` | Selesai | Memori operasional/teknis untuk AI agent. |

## Fokus lanjutan yang layak

1. Perluas pengujian otomatis. Saat snapshot ini, `tests/Feature/LoginKeamananTest.php` masih contoh dasar yang hanya meminta `/`; belum menguji kebijakan laporan, tenant scope, draf, notifikasi, atau force password change.
2. Audit konsistensi dokumentasi deployment lama. Sebagian `README` deployment masih memakai nama lama `SI Project TIK`/contoh `si-project-tik`, sementara Compose aktif berlabel MER System.
3. Verifikasi jalur queue produksi. Compose menyetel `QUEUE_CONNECTION=redis` dan supervisor tersedia; uji bahwa worker benar-benar aktif serta audit asinkron hanya diaktifkan jika queue `audit` diproses.
4. Tinjau pernyataan/komentar kode yang berpotensi tidak lagi sejalan dengan implementasi (misalnya komentar listener tentang `unit_id`) sebelum mengubah logika notifikasi.
5. Menetapkan owner bisnis untuk definisi status, SLA tindak lanjut, retensi operasional, dan prosedur eskalasi Sentinel.

## Cara melanjutkan dengan aman

- Baca `context/system-instructions.md`, lalu dokumen yang relevan dengan tugas.
- Mulai dari `git status --short`, bukan dari asumsi snapshot ini.
- Untuk perubahan perilaku, cari pemakaian status/peran dengan `rg` dan tambahkan test sebelum/bersamaan dengan perubahan.
- Jangan menjalankan perintah reset/fresh migration atau pembersihan volume tanpa persetujuan eksplisit karena dapat menghapus data.

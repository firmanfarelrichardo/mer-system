# Panduan Code Review

## Prioritas pemeriksaan

1. **Keamanan dan privasi:** tidak ada kebocoran data pasien, tenant, token, sandi, IP lengkap, atau `.env`.
2. **Otorisasi:** semua baca/tulis laporan tetap mengikuti tenant scope dan `InsidenPolicy`.
3. **Kebenaran alur bisnis:** draf, submit, status, notifikasi, audit, dan eskalasi tetap konsisten.
4. **Keutuhan data:** transaksi, migration, constraint, relasi, dan *soft delete* aman.
5. **Operasional:** perubahan Docker/queue/cache/migrasi dapat di-deploy dan di-rollback secara wajar.
6. **Kualitas:** test, typing, style, dokumentasi, dan UI yang responsif.

## Checklist khusus MER System

### Backend dan data

- [ ] Query domain selalu membatasi `tenant_id` atau memakai scope aman yang sudah ada.
- [ ] Akses laporan baru memakai `authorize`/policy dan bukan hanya kondisi Blade.
- [ ] Status memakai nilai kanonik: `DRAF`, `kasus_baru`, `investigasi`, `tindak_lanjut`, `selesai`.
- [ ] Draf tidak memicu notifikasi pihak lain; *auto-save* tidak menghasilkan spam audit observer.
- [ ] Perubahan `pelaporan.insiden` dan `detail_pasien` yang harus atomik dibungkus transaksi.
- [ ] Migration bersifat maju (*forward-only*), memiliki `down()` yang realistis bila diperlukan, dan tidak merusak data lama.
- [ ] Data rahasia/pasien tidak ikut pada pesan exception, log, fixture, screenshot, atau komentar.

### Peran dan notifikasi

- [ ] Peran aktif diperlakukan benar untuk dual-role/Peneliti (`memilikiPeran()` bersifat session-aware).
- [ ] Direktur tetap read-only untuk status biasa dan hanya dapat memberi solusi pada insiden eskalasi.
- [ ] Perubahan status memperbarui penerima pada `KirimNotifikasiInsiden` dan test-nya.
- [ ] Notifikasi tidak dikirim kepada pelaku sendiri jika perilaku itu tidak diinginkan.

### Frontend dan API internal

- [ ] Form memiliki validasi server-side; JS/Alpine hanya meningkatkan pengalaman pengguna.
- [ ] Endpoint *auto-save* hanya menerima AJAX/JSON dan menangani data parsial tanpa membuka akses lintas pengguna.
- [ ] Tampilan menggunakan escape Blade standar untuk konten pengguna/pasien.
- [ ] Perubahan view mempertimbangkan desktop dan mobile.

### Infrastruktur

- [ ] Tidak ada kredensial nyata pada Compose, dokumen, atau commit.
- [ ] Port production database/Redis tidak diubah menjadi terbuka publik tanpa keputusan keamanan eksplisit.
- [ ] Bila `QUEUE_CONNECTION=redis` atau audit async diubah, worker Supervisor/healthcheck/deploy ikut ditinjau.
- [ ] Konfigurasi staging dan production diperiksa terpisah; jangan memindahkan setting debug staging ke production.

## Perintah verifikasi minimum

Jalankan sesuai ketersediaan environment dan laporkan yang tidak dapat dijalankan:

```bash
composer test
./vendor/bin/phpstan analyse
npm run build
docker compose -f deployment/staging/docker-compose.yml config
docker compose -f deployment/production/docker-compose.yml config
```

Jangan menjalankan `migrate:fresh`, `db:wipe`, `docker compose down -v`, atau skrip cleanup destruktif sebagai bagian review tanpa otorisasi eksplisit.

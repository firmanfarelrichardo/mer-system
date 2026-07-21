# Arsitektur MER System

## Tujuan dan konteks

MER System adalah monolit Laravel untuk pelaporan *medication error*. Kode aplikasi berada dalam satu repository dan dipisahkan secara konseptual ke HTTP/controller, service, repository, model Eloquent, database PostgreSQL, serta infrastruktur Docker.

## Peta komponen

```text
Browser
  │ HTTPS (production melalui Cloudflare) / HTTP tunnel (staging)
  ▼
Nginx (web)
  ▼ FastCGI
Laravel 12 / PHP-FPM (app)
  ├─ Controllers + Requests + Policies
  ├─ Services + Repositories + DTOs
  ├─ Events / Listeners / Notifications / Observers
  ├─ DomPDF
  ├──────────── PostgreSQL 16 (schema tenant, akun, master, pelaporan, audit)
  └──────────── Redis 7 (cache, session, queue)

Vite + Tailwind CSS + Alpine.js membangun/menjalankan aset antarmuka.
```

## Lapisan kode

| Lokasi | Tanggung jawab |
| --- | --- |
| `routes/web.php` | Semua HTTP route; grup `guest`, `auth`, dan `force.password.change`. |
| `app/Http/Controllers/` | Orkestrasi halaman dan endpoint. `LaporanController` menangani alur laporan lintas peran. |
| `app/Http/Requests/` | Validasi dan pembatasan request, termasuk login dan laporan. |
| `app/Services/` | Logika bisnis; contoh `LaporanService`, `AuditLogService`, `NotifikasiService`. |
| `app/Repositories/` | Query data terfokus agar controller/service tidak mengulang query. |
| `app/DataTransferObjects/` | Pembentukan data tervalidasi/terstruktur antarlapisan. |
| `app/Models/` | Mapping tabel PostgreSQL skema-bernama, relasi, scope, accessor, cast. |
| `app/Policies/InsidenPolicy.php` | Otorisasi baca/tindak lanjut/eskalasi/solusi Direktur. |
| `app/Events`, `Listeners`, `Notifications` | Perubahan status dan notifikasi in-app. |
| `app/Observers` | Safety net audit pada mutasi model. |
| `resources/views` | Blade per halaman dan komponen dasbor/grafik. |

## Alur laporan inti

1. Pengguna terautentikasi membuka form `/laporan/buat`.
2. `SimpanLaporanRequest` dan `InsidenData` membentuk data; *auto-save* menggunakan DTO yang toleran terhadap data parsial.
3. `LaporanService` menjalankan transaksi: membuat/memperbarui `pelaporan.insiden` dan `pelaporan.detail_pasien`.
4. Draf berstatus `DRAF`; submit berstatus `kasus_baru`.
5. Audit dicatat dan, untuk submit, event `InsidenStatusBerubah` dikirim.
6. `KirimNotifikasiInsiden` memilih penerima menurut status dan peran/unit.
7. Tindak lanjut menggunakan `InsidenPolicy`, mencatat histori, memperbarui status, dan kembali memicu event/notifikasi.

## Isolasi akses data

`Insiden::scopeUntukPeran()` selalu membatasi `tenant_id`, lalu menerapkan scope berikut.

| Peran aktif | Visibilitas laporan |
| --- | --- |
| Admin, Komite, Direktur | Semua laporan tenant selain draf |
| Kepala Ruangan | Laporan pada `unit_id`/nama unit yang sama; nol data bila belum ditugaskan unit |
| Nakes | Laporan yang dibuat sendiri |
| Peneliti | Bergantung peran simulasi aktif atau halaman dasbor peneliti |

## Infrastruktur lingkungan

| Lingkungan | Compose | Service | Eksposur utama |
| --- | --- | --- | --- |
| Staging | `deployment/staging/docker-compose.yml` | `app`, `web`, `db`, `redis` | Nginx `127.0.0.1:8080`, DB `127.0.0.1:5433`; akses web lewat SSH tunnel |
| Production | `deployment/production/docker-compose.yml` | `app`, `web`, `db`, `redis` | Nginx `80`/`443`; DB/Redis hanya loopback; Cloudflare berada di depan origin |
| Monitoring | `docker-compose.monitoring.yml` per lingkungan | Grafana, Loki, Promtail, Uptime Kuma | Dipisahkan dari stack aplikasi |

Production menggunakan volume untuk public bersama, storage, log aplikasi, PostgreSQL, dan Redis. Staging menggunakan network/volume/container tersendiri agar tidak bercampur dengan production.

## Prinsip perubahan

- Pertahankan batas controller → service → repository/model; jangan menaruh query bisnis kompleks di Blade.
- Otorisasi harus tetap memakai policy/scope dan pembatasan tenant, bukan hanya menyembunyikan tombol UI.
- Bila menambah perubahan status, perbarui model label/warna, policy, listener notifikasi, test, dan dokumentasi alur.
- Perubahan skema harus melalui migration; `database/sql/mer_ddl.sql` adalah referensi struktur dasar dan harus dijaga selaras bila relevan.

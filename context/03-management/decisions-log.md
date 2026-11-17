# Log Keputusan Arsitektur dan Produk

Format: keputusan yang sudah tercermin pada kode/konfigurasi disebut **Ditetapkan**. Hal yang belum mempunyai bukti keputusan bisnis disebut **Perlu konfirmasi**, bukan fakta.

| ID | Status | Keputusan | Alasan dan konsekuensi |
| --- | --- | --- | --- |
| ADR-001 | Ditetapkan | Laravel 12 digunakan sebagai monolit web. | Memusatkan UI Blade, autentikasi, domain, PDF, queue, dan migrasi dalam satu aplikasi; perubahan fitur lintas lapisan tetap dikelola dalam satu repo. |
| ADR-002 | Ditetapkan | PostgreSQL multi-schema digunakan: `tenant`, `akun`, `master`, `pelaporan`, `audit`. | Memisahkan domain tanpa banyak database; query/model harus memakai nama tabel ber-skema dan tenant boundary. |
| ADR-003 | Ditetapkan | Model data siap multi-tenant, tetapi bukan berarti fitur multi-tenant UI otomatis selesai. | `tenant_id` dan constraint digunakan luas; setiap query baru harus aman terhadap kebocoran lintas tenant. |
| ADR-004 | Ditetapkan | RBAC memakai relasi many-to-many pengguna–peran dan peran aktif berbasis session. | Mendukung dual-role dan Peneliti simulasi; cek hak akses harus memakai `memilikiPeran()`/policy, bukan asumsi peran pertama. |
| ADR-005 | Ditetapkan | Laporan draf memakai status `DRAF`, bukan prefix nomor terpisah. | Nomor `INC-...` digenerate sejak pembuatan awal dan tetap saat submit; draf tidak menotifikasi pihak lain. |
| ADR-006 | Ditetapkan | Otorisasi laporan dilakukan dua lapis: scope query dan `InsidenPolicy`. | Scope membatasi visibilitas data, policy membatasi tindakan; jangan mengganti dengan sekadar kontrol UI. |
| ADR-007 | Ditetapkan | Kepala Ruangan dan Komite memiliki penyelesaian tindak lanjut independen per peran. | Status terakhir oleh peran digunakan agar satu peran selesai tidak mengunci peran lain. |
| ADR-008 | Ditetapkan | Redis dipakai untuk cache, session, dan queue pada staging/production. | Redis harus sehat dan berpassword; queue worker perlu diverifikasi ketika fungsi asinkron diaktifkan. |
| ADR-009 | Ditetapkan | Audit log dipertahankan default 5 tahun dan bisa asinkron. | Mendukung auditabilitas; ketika `AUDIT_LOG_ASYNC=true`, worker queue `audit` menjadi prasyarat operasional. |
| ADR-010 | Ditetapkan | Production memakai Nginx + Cloudflare Full (Strict) dengan port 80/443 origin. | Sertifikat Cloudflare dimount read-only dari `/etc/ssl/cloudflare`; database/Redis tidak dibuka ke internet. |
| ADR-011 | Ditetapkan | Staging diisolasi dan hanya dibuka lewat loopback/SSH tunnel. | Mengurangi paparan sistem uji dan mencegah campur data/network dengan production. |
| ADR-012 | Perlu konfirmasi | SLA tiap status, definisi selesai, dan kewajiban eskalasi Sentinel. | Kode mengatur hak dan notifikasi, namun durasi respons/owner kebijakan belum ditemukan sebagai aturan eksplisit. |

## Aturan mencatat keputusan baru

Tambahkan baris baru bila perubahan memengaruhi kontrak produk, model data, keamanan, deployment, atau pola kerja agent. Tulis opsi yang ditolak bila keputusan sulit dibalik, dan tautkan PR/issue/commit bila tersedia. Jangan menulis rahasia atau data pasien.

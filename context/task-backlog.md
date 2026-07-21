# Backlog Teknis dan Produk

## Cara menggunakan

Backlog ini adalah daftar kerja, bukan bukti bahwa suatu isu pasti terjadi di production. Sebelum mulai, validasi dengan kode, test, log aman, atau pemilik bisnis. Beri ID/tautan issue saat pekerjaan diadopsi.

## Prioritas tinggi

| ID | Item | Nilai | Kriteria selesai |
| --- | --- | --- | --- |
| BKL-001 | Bangun suite test domain laporan dan RBAC | Mencegah regresi akses/privasi | Ada feature test untuk tenant isolation, scope per peran, draf, policy tindak lanjut/eskalasi, dan force password change. |
| BKL-002 | Verifikasi worker queue production dan audit async | Mencegah job/audit tertinggal | Dokumen runbook + health/verifikasi worker; konfigurasi audit async hanya diaktifkan bila queue `audit` diproses. |
| BKL-003 | Audit dokumentasi deployment yang masih memakai nama lama | Mengurangi kesalahan operasional | README contoh `SI Project TIK`/`si-project-tik` diselaraskan dengan MER System dan Compose aktif. |
| BKL-004 | Uji alur notifikasi end-to-end | Menjamin pihak tepat mendapat informasi | Test/cek kasus baru, investigasi Sentinel, tindak lanjut, selesai, pengecualian pelaku, serta draf tanpa notifikasi. |

## Prioritas menengah

| ID | Item | Nilai | Kriteria selesai |
| --- | --- | --- | --- |
| BKL-005 | Tetapkan SLA dan definisi bisnis status | Kejelasan operasional | Pemilik bisnis menyetujui pemicu status, batas waktu, definisi selesai, dan eskalasi Sentinel; PRD/alur diperbarui. |
| BKL-006 | Tinjau data minimization pada PDF/ekspor/log | Kepatuhan privasi | Akses dan isi keluaran dikaji per peran; data sensitif yang tidak diperlukan disamarkan/dihilangkan. |
| BKL-007 | Tambahkan test smoke deployment/healthcheck | Keandalan rilis | Pipeline memvalidasi Compose config dan endpoint health sesuai lingkungan. |
| BKL-008 | Dokumentasikan backup-restore yang diuji | Kesiapan pemulihan | Ada RPO/RTO yang disetujui dan bukti uji restore PostgreSQL/storage. |

## Prioritas rendah / eksplorasi

| ID | Item | Nilai | Kriteria selesai |
| --- | --- | --- | --- |
| BKL-009 | Evaluasi integrasi HIS/EMR | Mengurangi input ulang | Kontrak integrasi, security review, dan consent/data governance disepakati sebelum implementasi. |
| BKL-010 | Evaluasi kanal notifikasi tambahan | Respons lebih cepat | Email/kanal lain tidak diaktifkan sebelum template, opt-in, kredensial, retry, dan monitoring siap. |
| BKL-011 | Evaluasi multi-tenant operasional | Skalabilitas organisasi | Isolation, provisioning, admin scope, serta migrasi data diuji sebelum lebih dari satu organisasi aktif. |

## Definisi selesai lintas backlog

- Kebutuhan dan owner jelas.
- Perubahan teruji pada level yang sesuai serta tidak menyertakan data sensitif.
- Dokumentasi context/PRD/runbook diperbarui bila kontrak berubah.
- Rencana rollback atau mitigasi tersedia untuk perubahan data/infrastruktur.

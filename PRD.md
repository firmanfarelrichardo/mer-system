# PRD — MER System

## 1. Ringkasan produk

**MER System** (*Medication Error Reporting System*) adalah aplikasi web internal rumah sakit untuk mencatat, menindaklanjuti, memantau, dan menganalisis insiden kesalahan pengobatan secara terstruktur. Aplikasi membantu rumah sakit membangun budaya pelaporan yang aman melalui pelaporan teridentifikasi maupun anonim, alur tindak lanjut berbasis peran, jejak audit, dan data ringkasan untuk pengambilan keputusan.

Dokumen ini adalah sumber kebutuhan produk tingkat tinggi. Detail implementasi, arsitektur, skema data, dan aturan kerja agent berada di folder [`context/`](context/).

## 2. Masalah yang diselesaikan

- Pelaporan insiden dapat terlambat, tidak seragam, atau hilang bila masih berbasis formulir/manual.
- Pihak yang tepat (Kepala Ruangan, Komite, Direktur) membutuhkan visibilitas dan tindakan sesuai kewenangannya.
- Rumah sakit membutuhkan riwayat aktivitas yang dapat ditelusuri, tanpa mengekspos data pasien atau pelapor secara berlebihan.
- Data insiden perlu dapat direkap menjadi statistik dan dokumen PDF untuk evaluasi mutu serta keselamatan pasien.

## 3. Sasaran dan batasan

### Sasaran

1. Memudahkan Nakes dan Kepala Ruangan membuat laporan insiden, termasuk draf dan *auto-save*.
2. Mengarahkan laporan dan notifikasi kepada pemangku kepentingan berdasarkan status, unit kerja, tingkat insiden, dan peran.
3. Membatasi akses data dan tindakan berdasarkan tenant serta peran aktif pengguna.
4. Menyediakan dasbor, statistik, rekap PDF, notifikasi, dan audit log untuk operasional rumah sakit.
5. Siap dioperasikan di staging dan production dengan Docker, PostgreSQL, Redis, logging, monitoring, backup, dan Cloudflare.

### Di luar ruang lingkup saat ini

- Integrasi langsung dengan EMR/HIS, farmasi, atau identitas rumah sakit.
- REST/GraphQL API publik atau aplikasi mobile native.
- Pengiriman email sebagai kanal notifikasi aktif; UI pengaturan email menyatakan fitur ini masih tertunda.
- Multi-organisasi aktif pada UI. Model data telah *multi-tenant ready*, tetapi konfigurasi/seeder saat ini berorientasi pada satu organisasi.

## 4. Pengguna dan kebutuhan

| Pengguna | Kebutuhan utama | Hak akses produk |
| --- | --- | --- |
| Nakes | Melapor dengan cepat dan aman; melihat perkembangan laporan sendiri | Buat/kirim draf, lihat laporan sendiri, terima notifikasi |
| Kepala Ruangan | Memverifikasi insiden unit dan memberi tindak lanjut | Lihat laporan unit, ubah status/catatan, dapat notifikasi kasus baru |
| Komite | Menginvestigasi, memantau seluruh tenant, dan eskalasi | Lihat semua laporan tenant, tindak lanjut, eskalasi Direktur, ekspor |
| Direktur | Memantau risiko dan memberi arahan pada kasus eskalasi | Baca semua laporan tenant, isi solusi Direktur, ekspor; bukan pengubah status biasa |
| Admin | Mengelola identitas, organisasi master, dan audit | Dasbor admin, pengguna, peran/unit, master data, log aktivitas |
| Peneliti | Menguji/meninjau pengalaman tiap peran secara terkendali | Dasbor peneliti dan simulasi seluruh peran melalui sesi |

## 5. Kebutuhan fungsional

### FR-01 — Akses dan keamanan akun

- Sistem menyediakan login, logout normal, dan logout karena *idle*.
- Login dibatasi maksimal lima percobaan dalam sepuluh menit pada `LoginRequest`.
- Pengguna nonaktif tidak dapat menggunakan sistem.
- Admin dapat mengatur ulang sandi dan membuka blokir login.
- Flag `wajib_ganti_sandi` memaksa pengguna mengubah sandi sebelum mengakses fitur terproteksi.
- Pengguna dual-role dapat memilih peran aktif; Peneliti dapat mensimulasikan seluruh peran tanpa mengubah peran di basis data.

### FR-02 — Pelaporan insiden

- Formulir menerima data kejadian, unit kerja, pelapor/anonimitas, pasien, obat/dosis, kronologi, jenis kesalahan, cedera, penyebab, dan intervensi.
- Laporan baru mendapat nomor unik `INC-YYYYMMDD-HHmmss-XXXX` sejak pertama kali disimpan, termasuk draf.
- Pengguna dapat menyimpan draf secara eksplisit atau dengan *auto-save* AJAX (de-bounce dua detik).
- Draf tidak mengirim notifikasi dan hanya dapat dilanjutkan oleh pelapor pemiliknya.
- Laporan terkirim berstatus `kasus_baru` dan dapat dicetak menjadi PDF.

### FR-03 — Alur tindak lanjut dan eskalasi

- Status utama: `DRAF`, `kasus_baru`, `investigasi`, `tindak_lanjut`, dan `selesai`.
- Kepala Ruangan dan Komite dapat menambahkan tindak lanjut; penyelesaian dibatasi per peran agar penyelesaian satu peran tidak memblokir peran lain.
- Komite dapat mengeskalasi insiden ke Direktur.
- Direktur hanya dapat menambahkan solusi/arahannya bila insiden telah dieskalasi.
- Kepala Ruangan, Komite, dan Admin dapat menandai laporan telah dibaca sesuai aturan policy.

### FR-04 — Notifikasi dan observabilitas aktivitas

- Kasus baru memberi notifikasi kepada Kepala Ruangan unit terkait dan Komite.
- Investigasi memberi notifikasi Komite dan pelapor; *Sentinel event* juga memberi notifikasi Direktur.
- Tindak lanjut dan penyelesaian memberi notifikasi pelapor; penyelesaian juga memberi notifikasi Kepala Ruangan unit terkait.
- Semua perubahan penting dicatat ke audit log; penyimpanan audit dapat sinkron atau melalui queue `audit`.

### FR-05 — Administrasi, analitik, dan keluaran

- Admin mengelola pengguna, unit kerja, kategori, jenis kesalahan, tipe cedera, faktor penyebab, intervensi, penetapan Kepala Ruangan, dan log aktivitas.
- Dasbor per peran dan halaman statistik menyediakan ringkasan insiden sesuai ruang aksesnya.
- Komite dan Direktur dapat mengekspor rekapitulasi PDF dalam rentang tanggal.
- Command `pengguna:export-production` menghasilkan SpreadsheetML `.xls` data pengguna; jalankan hanya pada lingkungan dan lokasi keluaran yang disetujui.

## 6. Kebutuhan nonfungsional

| Area | Kebutuhan |
| --- | --- |
| Privasi | Data pasien/pelapor hanya tersedia bagi peran dan tenant yang berhak; IP/user agent audit dimasking kecuali Admin. |
| Keamanan | HTTPS production, Cloudflare Full (Strict), sandi di-hash Laravel, session aman, Redis berpassword, database tidak dibuka ke internet. |
| Keandalan | Transaksi DB untuk penyimpanan laporan, healthcheck Docker, volume persisten, migrasi terkontrol, backup PostgreSQL. |
| Kinerja | Redis untuk cache/session/queue; pagination; *auto-save* menghindari spam observer; image production dan OPcache dioptimalkan. |
| Auditabilitas | Audit log retensi default 1.825 hari (5 tahun); setiap mutasi penting dapat ditelusuri. |
| Operasional | Staging terisolasi dari production; Loki/Promtail/Grafana/Uptime Kuma tersedia sebagai stack monitoring terpisah. |
| Lokalitas | Bahasa antarmuka dan dokumentasi operasional utama Bahasa Indonesia; zona waktu `Asia/Jakarta`. |

## 7. Kriteria penerimaan ringkas

1. Nakes dapat login, membuat draf, keluar-masuk kembali, lalu mengirim laporan tanpa kehilangan data inti.
2. Laporan terkirim tidak terlihat sebagai draf dan dapat dilihat sesuai scope Nakes/Karu/Komite/Direktur.
3. Notifikasi dan audit tercipta sesuai perubahan status, sedangkan draf tidak memberi notifikasi kepada pihak lain.
4. Direktur tidak dapat mengubah status umum dan hanya dapat mengisi solusi atas kasus yang telah dieskalasi.
5. Deploy staging dan production menjalankan aplikasi, database, Redis, serta healthcheck melalui Compose masing-masing.
6. Suite test, analisis statis, dan build aset lulus sebelum rilis.

## 8. Tautan konteks implementasi

- [Index & Navigasi Context](context/README.md)
- [Arsitektur](context/01-project/architecture.md)
- [Skema basis data](context/01-project/database-schema.md)
- [Stack teknologi](context/01-project/tech-stack.md)
- [Alur fitur per pemangku kepentingan](context/01-project/stakeholder-features-flow.md)
- [Instruksi sistem untuk AI agent](context/02-development/system-instructions.md)
- [Konvensi kode](context/02-development/coding-conventions.md)
- [Panduan pengujian](context/02-development/testing-guide.md)
- [Panduan code review](context/02-development/code-review.md)
- [Backlog](context/03-management/task-backlog.md)
- [Log keputusan](context/03-management/decisions-log.md)
- [Progres saat ini](context/03-management/current-progress.md)

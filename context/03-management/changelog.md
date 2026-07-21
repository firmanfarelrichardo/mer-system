
# Changelog Context

Dokumen ini adalah riwayat perubahan khusus folder `context/`. Semua waktu menggunakan **WIB (UTC+07:00)** dan dicatat dengan format `YYYY-MM-DD HH:MM:SS WIB`.

## Aturan pencatatan

- Tambahkan entri pada saat yang sama dengan setiap penambahan, perubahan substansial, penggantian nama, atau penghapusan file di folder `context/`.
- Catat waktu lokal aktual, file terdampak, ringkasan perubahan, dan alasan bila relevan.
- Untuk beberapa file yang dibuat dalam satu operasi, satu entri dapat mencantumkan seluruh daftar file.
- Jangan mencatat data pasien, kredensial, token, atau informasi rahasia dalam changelog.

## Riwayat

### 2026-07-21 21:12:00 WIB

- **Restrukturisasi besar folder `context/`** — reorganisasi seluruh isi agar terstruktur, scalable, dan multi-agent ready.
- Perubahan struktur:
  - Membuat subfolder kategori: `01-project/`, `02-development/`, `03-management/`, `04-agent-output/`
  - Memindahkan semua file ke subfolder yang sesuai
  - Menghapus folder lama `Output Agent/` (kosong, nama tidak filesystem-friendly)
  - Menghapus semua file lama di root `context/`
- File baru:
  - `README.md` — index/manifest dan panduan navigasi untuk AI agent
  - `CONTRIBUTING.md` — panduan kontribusi dokumentasi context
  - `01-project/tech-stack.md` — stack teknologi, dependencies, dan mapping source code
  - `02-development/coding-conventions.md` — konvensi kode dan penamaan MER System
  - `04-agent-output/.gitkeep` — placeholder agar folder ter-track git
- File diperbaiki:
  - `currenct-progress.md` → `03-management/current-progress.md` (fix typo nama file, hapus catatan internal tentang typo, update referensi)
  - `system-instructions.md` → `02-development/system-instructions.md` (fix broken references ke path baru, tambah tabel navigasi cepat)
- Referensi eksternal diperbarui:
  - `PRD.md` section 8 — link diperbarui ke path baru

### 2026-07-21 19:50:08–19:50:10 WIB

- Menambahkan `changelog.md` sebagai mekanisme pencatatan perubahan folder context.
- Menetapkan format waktu WIB (UTC+07:00) dan aturan pembaruan changelog untuk perubahan context di masa depan.

### 2026-07-21 19:41:13–19:41:26 WIB

- Menambahkan fondasi memori AI agent:
  - `architecture.md`
  - `currenct-progress.md`
  - `database-schema.md`
  - `decisions-log.md`
  - `code-review.md`
  - `stakeholder-features-flow.md`
  - `system-instructions.md`
  - `task-backlog.md`
  - `testing-guide.md`
- Isi awal mendokumentasikan arsitektur, aturan domain, skema basis data, alur pemangku kepentingan, keputusan, kualitas, pengujian, dan backlog MER System.

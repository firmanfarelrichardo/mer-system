# Context — MER System

> Dokumentasi konteks project untuk AI agent dan tim pengembang.
> Terakhir diperbarui: 2026-07-21 21:01 WIB

## Cara Menggunakan (Quick Start untuk AI Agent)

**Urutan baca yang direkomendasikan:**

1. **File ini** (`context/README.md`) — peta navigasi dan ringkasan seluruh konteks
2. **`02-development/system-instructions.md`** — aturan wajib sebelum melakukan perubahan
3. **Dokumen yang relevan dengan tugas** — pilih dari peta di bawah

> [!IMPORTANT]
> Selalu baca `system-instructions.md` sebelum mengubah kode. Dokumen tersebut berisi aturan domain, keamanan, dan alur kerja yang **tidak boleh dilanggar**.

## Peta Navigasi

### 📁 `01-project/` — Informasi Project

Dokumen yang menjelaskan **apa** yang dibangun dan **bagaimana** arsitekturnya.

| File | Deskripsi | Kapan Dibaca |
|------|-----------|--------------|
| [`architecture.md`](01-project/architecture.md) | Arsitektur sistem, peta komponen, lapisan kode, alur laporan inti, isolasi akses data, dan infrastruktur lingkungan | Sebelum mengubah arsitektur, menambah komponen, atau memahami alur data |
| [`database-schema.md`](01-project/database-schema.md) | Skema PostgreSQL multi-schema, entitas/relasi, tabel inti, konvensi data, status/nilai referensi, dan perlindungan data | Sebelum mengubah model, migration, query, atau struktur data |
| [`stakeholder-features-flow.md`](01-project/stakeholder-features-flow.md) | Matriks kemampuan per peran, alur pelaporan dan tindak lanjut, rincian alur per peran (Nakes, Karu, Komite, Direktur, Admin, Peneliti) | Sebelum mengubah fitur, otorisasi, notifikasi, atau alur bisnis |
| [`tech-stack.md`](01-project/tech-stack.md) | Stack teknologi, dependencies, tools, dan mapping direktori source code ↔ tanggung jawab | Untuk referensi cepat stack dan lokasi kode |

### 📁 `02-development/` — Panduan Development

Aturan dan panduan **bagaimana** melakukan perubahan dengan benar.

| File | Deskripsi | Kapan Dibaca |
|------|-----------|--------------|
| [`system-instructions.md`](02-development/system-instructions.md) | **⚠️ WAJIB BACA** — Peran agent, urutan kerja wajib, aturan domain, keamanan, privasi, praktik implementasi, dan batas tindakan berisiko | **Selalu** sebelum melakukan perubahan apapun |
| [`code-review.md`](02-development/code-review.md) | Checklist code review khusus MER System: backend/data, peran/notifikasi, frontend, infrastruktur, dan perintah verifikasi | Saat melakukan atau mereview perubahan kode |
| [`testing-guide.md`](02-development/testing-guide.md) | Panduan pengujian: perintah dasar, matriks skenario minimum, pengujian manual UI, staging/deploy, dan pelaporan hasil | Saat menulis test atau memverifikasi perubahan |
| [`coding-conventions.md`](02-development/coding-conventions.md) | Konvensi kode: penamaan, pattern (Service/Repository/DTO/Policy), struktur file, dan Blade/view | Saat menulis kode baru atau mereview konsistensi |

### 📁 `03-management/` — Manajemen Project

Dokumen yang melacak **status**, **keputusan**, dan **rencana** project.

| File | Deskripsi | Kapan Dibaca |
|------|-----------|--------------|
| [`current-progress.md`](03-management/current-progress.md) | Snapshot repository, apa yang sudah didokumentasikan, fokus lanjutan, dan cara melanjutkan dengan aman | Di awal sesi kerja untuk memahami titik mulai |
| [`task-backlog.md`](03-management/task-backlog.md) | Backlog teknis dan produk: prioritas tinggi/menengah/rendah dengan ID, nilai, dan kriteria selesai | Saat mencari tugas berikutnya atau menambah item baru |
| [`decisions-log.md`](03-management/decisions-log.md) | Log keputusan arsitektur dan produk (ADR) dengan status Ditetapkan/Perlu konfirmasi | Sebelum membuat keputusan arsitektur atau mengubah kontrak |
| [`changelog.md`](03-management/changelog.md) | Riwayat perubahan folder `context/` dengan timestamp WIB | **Wajib diupdate** setiap kali file context ditambah/diubah/dihapus |

### 📁 `04-agent-output/` — Output AI Agent

Folder untuk menyimpan output terstruktur dari sesi kerja AI agent.

| Konten | Deskripsi |
|--------|-----------|
| File output sesi | Hasil analisis, laporan, rekomendasi dari sesi AI agent. Lihat [`CONTRIBUTING.md`](CONTRIBUTING.md) untuk format template |

## Cross-Reference: Context ↔ Source Code

| Dokumen Context | File/Direktori Source Code Terkait |
|-----------------|-----------------------------------|
| `architecture.md` | `routes/web.php`, `app/Http/Controllers/`, `app/Services/`, `app/Repositories/`, `deployment/` |
| `database-schema.md` | `database/migrations/`, `database/sql/mer_ddl.sql`, `app/Models/` |
| `stakeholder-features-flow.md` | `app/Policies/InsidenPolicy.php`, `app/Events/`, `app/Listeners/`, `app/Notifications/` |
| `system-instructions.md` | Seluruh codebase (aturan universal) |
| `code-review.md` | `app/Http/Requests/`, `app/Policies/`, `deployment/`, `tests/` |
| `testing-guide.md` | `tests/`, `phpunit.xml`, `phpstan.neon` |
| `coding-conventions.md` | `app/` (seluruh lapisan kode) |

## Dokumen Terkait di Luar Folder Context

| File | Lokasi | Deskripsi |
|------|--------|-----------|
| `PRD.md` | Root project | Product Requirements Document — kebutuhan produk dan kriteria penerimaan |
| `README.md` | Root project | README project (saat ini masih template Laravel default) |
| `documentation/` | Root project | Dokumentasi operasional deployment (01–07) |

## Aturan Pemeliharaan

1. **Update `changelog.md`** setiap kali file di folder `context/` ditambah, diubah substansial, atau dihapus
2. **Update file ini** (`README.md`) bila menambah/menghapus/memindahkan file context
3. **Ikuti panduan** di `CONTRIBUTING.md` untuk format dan konvensi penulisan
4. **Jangan menyimpan** data pasien, kredensial, token, atau informasi rahasia di dokumen context manapun

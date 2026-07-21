# Panduan Kontribusi Dokumentasi Context

> Panduan untuk menambah, mengubah, dan memelihara dokumen di folder `context/`.

## Struktur Folder

```
context/
├── README.md              ← Index/manifest (update saat file ditambah/dihapus)
├── CONTRIBUTING.md        ← File ini
├── 01-project/            ← Informasi arsitektur, skema, alur bisnis
├── 02-development/        ← Panduan development, review, testing
├── 03-management/         ← Progress, backlog, keputusan, changelog
└── 04-agent-output/       ← Output sesi AI agent
```

## Konvensi Penamaan File

- Gunakan **lowercase** dengan **dash** sebagai pemisah kata: `nama-file.md`
- Gunakan ekstensi `.md` (Markdown)
- Nama harus deskriptif dan singkat: `database-schema.md`, bukan `db.md` atau `dokumentasi-lengkap-skema-basis-data-postgresql.md`
- **Jangan** menggunakan spasi, underscore, atau karakter khusus dalam nama file/folder

## Format Header Standar

Setiap file context **harus** memiliki header level-1 sebagai judul:

```markdown
# Judul Dokumen

> Deskripsi singkat satu baris tentang isi dokumen.
```

## Aturan Penulisan Konten

### Bahasa

- Gunakan **Bahasa Indonesia** yang spesifik dan teknis
- Istilah teknis dalam bahasa Inggris boleh dipertahankan jika lebih umum (misalnya: migration, query, scope, policy, tenant, service, repository)
- Bedakan **fakta terverifikasi** dari **asumsi** dengan jelas

### Keamanan dan Privasi

- **DILARANG** menyertakan: data pasien, kredensial, token, API key, password, dump database, IP address lengkap, atau isi `.env`
- Gunakan placeholder atau contoh sintetis jika perlu ilustrasi
- Jika ragu, jangan sertakan

### Pembaruan

- Buat perubahan **minimal dan terfokus** — jangan menulis ulang seluruh dokumen jika hanya satu bagian yang berubah
- Perbarui **`03-management/changelog.md`** dengan timestamp WIB aktual setiap kali file context ditambah, diubah substansial, dipindahkan, atau dihapus
- Perbarui **`README.md`** jika menambah atau menghapus file context

## Kapan Membuat File Baru vs Update Existing

| Situasi | Tindakan |
|---------|----------|
| Informasi terkait topik yang sudah ada (misalnya arsitektur baru) | Update file yang sudah ada |
| Topik baru yang tidak cocok di file manapun | Buat file baru di subfolder yang sesuai |
| Output dari sesi AI agent | Simpan di `04-agent-output/` |
| Keputusan arsitektur baru | Tambahkan baris di `03-management/decisions-log.md` |
| Task/bug baru yang ditemukan | Tambahkan entry di `03-management/task-backlog.md` |

## Template Output AI Agent

Untuk menyimpan output sesi AI agent di `04-agent-output/`, gunakan format berikut:

**Penamaan file:** `YYYY-MM-DD-deskripsi-singkat.md`

**Template:**

```markdown
# [Judul Pekerjaan]

> Output dari sesi AI agent pada YYYY-MM-DD HH:MM WIB.

## Metadata

- **Tanggal:** YYYY-MM-DD HH:MM WIB
- **Agent:** [nama/jenis agent yang digunakan]
- **Tujuan:** [deskripsi singkat tujuan sesi]
- **Status:** [Selesai / Sebagian / Perlu Tindak Lanjut]

## Ringkasan

[Ringkasan singkat apa yang dilakukan dan hasilnya]

## Detail Perubahan

[Daftar file yang diubah, dibuat, atau dihapus beserta penjelasan]

## Temuan dan Rekomendasi

[Hal-hal yang ditemukan selama sesi, termasuk masalah, risiko, atau peluang perbaikan]

## Tindak Lanjut

- [ ] [Item yang perlu ditindaklanjuti]
```

## Checklist Sebelum Commit Perubahan Context

- [ ] Konten akurat dan terkini sesuai kode yang ada
- [ ] Tidak ada data sensitif (pasien, kredensial, token)
- [ ] `changelog.md` sudah diperbarui dengan entry baru
- [ ] `README.md` sudah diperbarui jika ada file baru/dihapus
- [ ] Link internal antar dokumen valid
- [ ] Penamaan file mengikuti konvensi (lowercase, dash-separated)

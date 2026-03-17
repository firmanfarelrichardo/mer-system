# Panduan Export Tabel Pengguna ke CSV (Staging)

Dokumen ini berisi langkah-langkah detail untuk mengekspor data dari tabel `pengguna` di database PostgreSQL yang berjalan di dalam Docker container staging (`mer-db-staging`) di VPS Anda.

## Prasyarat
Pastikan Anda memiliki akses SSH ke VPS Anda (`mer-vps`).

## Langkah-langkah

### 1. Masuk ke VPS melalui SSH
Buka terminal di komputer lokal Anda, lalu masuk ke VPS menggunakan SSH:
```bash
ssh mer-vps
```

### 2. Ekspor Tabel ke CSV dari dalam Container
Gunakan perintah `docker exec` untuk menjalankan perintah PostgreSQL `\copy` di dalam container `mer-db-staging`. Perintah ini akan mengekspor tabel `pengguna` ke folder sementara (`/tmp`) di dalam container.

Jalankan perintah berikut di terminal VPS:
```bash
docker exec -it mer-db-staging psql -U <DB_USER> -d <DB_NAME> -c "\copy (SELECT p.nama_lengkap, p.username, u.nama_unit AS unit_kerja, STRING_AGG(pr.nama_peran, ', ') AS peran FROM akun.pengguna p LEFT JOIN master.unit_kerja u ON p.unit_id = u.id LEFT JOIN akun.pengguna_peran pp ON p.id = pp.pengguna_id LEFT JOIN akun.peran pr ON pp.peran_id = pr.id GROUP BY p.id, u.nama_unit) TO '/tmp/pengguna-staging.csv' WITH CSV HEADER;"
```
*Catatan: Ganti `<DB_USER>` dengan username database Anda (contoh: `mer_dbadmin` atau `postgres`) dan `<DB_NAME>` dengan nama database Anda (contoh: `mer_production`).*

Atau, jika Anda ingin langsung menghasilkan file CSV di *host* VPS tanpa menyimpannya di `/tmp` container, Anda bisa menggunakan perintah ini (ini adalah cara yang lebih cepat):

```bash
docker exec -t mer-db-staging psql -U <DB_USER> -d <DB_NAME> -c "COPY (SELECT p.nama_lengkap, p.username, u.nama_unit AS unit_kerja, STRING_AGG(pr.nama_peran, ', ') AS peran FROM akun.pengguna p LEFT JOIN master.unit_kerja u ON p.unit_id = u.id LEFT JOIN akun.pengguna_peran pp ON p.id = pp.pengguna_id LEFT JOIN akun.peran pr ON pp.peran_id = pr.id GROUP BY p.id, u.nama_unit) TO STDOUT WITH CSV HEADER" > ~/pengguna-staging.csv
```

### 3. Salin File CSV dari Container ke VPS (Jika Menggunakan Cara `/tmp` di atas)
Jika Anda menggunakan cara pertama pada Langkah 2, salin file CSV dari dalam container ke direktori home (`~`) VPS Anda:
```bash
docker cp mer-db-staging:/tmp/pengguna-staging.csv ~/pengguna-staging.csv
```
Setelah itu, Anda bisa menghapus file sementara di dalam container untuk menghemat ruang:
```bash
docker exec -it mer-db-staging rm /tmp/pengguna-staging.csv
```

## Mengambil Langsung ke Komputer Lokal (Satu Baris Perintah)

Jika Anda ingin langsung mengambil data CSV ini dan menyimpannya ke komputer tujuan (tanpa menyimpannya sedikitpun di file container ataupun VPS).

Anda bisa mengatasinya lewat perintah SSH dan `docker exec` ini langsung dari terminal lokal Anda.
Berikut perintahnya:
```bash
ssh mer-vps "docker exec -i mer-db-staging psql -U <DB_USER> -d <DB_NAME> -c \"COPY (SELECT p.nama_lengkap, p.username, u.nama_unit AS unit_kerja, STRING_AGG(pr.nama_peran, ', ') AS peran FROM akun.pengguna p LEFT JOIN master.unit_kerja u ON p.unit_id = u.id LEFT JOIN akun.pengguna_peran pp ON p.id = pp.pengguna_id LEFT JOIN akun.peran pr ON pp.peran_id = pr.id GROUP BY p.id, u.nama_unit) TO STDOUT WITH CSV HEADER\"" > pengguna-staging.csv
```
Setelah dijalankan, file `pengguna-staging.csv` akan langsung terbentuk di folder saat ini tempat Anda menjalankan perintah tersebut.

> **Catatan**: 
> - Anda perlu mengganti `<DB_USER>` (contoh `mer_dbadmin`) dan `<DB_NAME>` (contoh `mer_production`) sesuai nama database staging Anda. 
> - Atau, Anda bisa menjalankan script singkat yang sudah disiapkan: `./deployment/staging/export-pengguna-local.sh`


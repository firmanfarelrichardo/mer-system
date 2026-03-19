#!/bin/bash
# Script untuk mengekspor tabel pengguna dari database staging di VPS langsung ke komputer lokal

DB_USER="mer_dbadmin"       # Ganti dengan username database Anda jika berbeda
DB_NAME="mer_production"    # Ganti dengan nama database Anda jika berbeda
OUTPUT_FILE="pengguna-staging.csv"

echo "Mulai mengunduh tabel pengguna dari VPS ke $OUTPUT_FILE..."

# Query SQL untuk mengambil data spesifik dengan format join tabel
SQL_QUERY="COPY (
    SELECT 
        p.nama_lengkap, 
        p.username, 
        u.nama_unit AS unit_kerja, 
        STRING_AGG(pr.nama_peran, ', ') AS peran 
    FROM akun.pengguna p 
    LEFT JOIN master.unit_kerja u ON p.unit_id = u.id 
    LEFT JOIN akun.pengguna_peran pp ON p.id = pp.pengguna_id 
    LEFT JOIN akun.peran pr ON pp.peran_id = pr.id 
    GROUP BY p.id, u.nama_unit
) TO STDOUT WITH CSV HEADER"

# Menjalankan perintah ssh dan meneruskan outputnya (STDOUT) langsung ke file lokal
ssh mer-vps "docker exec -i mer-db-staging psql -U $DB_USER -d $DB_NAME -c \"$SQL_QUERY\"" > "$OUTPUT_FILE"

if [ $? -eq 0 ]; then
    echo "Berhasil! Data telah disimpan di $OUTPUT_FILE"
else
    echo "Terjadi kesalahan saat mengunduh data."
    # Jika gagal karena username/db name salah, hapus file kosong
    rm -f "$OUTPUT_FILE"
fi

#!/bin/bash
# Script helper untuk menyalakan Docker dari folder local

# 1. Pindah ke folder script ini berada (deployment/local)
cd "$(dirname "$0")"

# 2. Jalankan Docker Compose dengan menunjuk lokasi .env yang benar
# Tambahkan flag: --env-file ../../.env
echo "Menyalakan server..."
docker compose --env-file ../../.env up -d --build

# 3. Info
echo "---------------------------------------"
echo "Server siap! Buka: http://localhost"
echo "Untuk mematikan: docker compose down"
echo "---------------------------------------"
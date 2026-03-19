#!/bin/bash
# =========================================================================
# MER System - Monitoring Tunnels Quick Start
# Script untuk membuka jalur aman dari komputer lokal ke VPS Monitoring
# =========================================================================

# Konfigurasi Server (Silakan sesuaikan IP_VPS jika ada perubahan)
USER="mer_ops"
IP_VPS="148.230.102.26"
SSH_PORT="49152"

# Port Forwarding Mapping
# Port Lokal : Port VPS Server
GRAFANA_PORT="3000"
UPTIME_PORT="3001"
NETDATA_PORT="19999"

echo "======================================================="
echo "   🛡️  MEMULAI KONEKSI TUNNEL KE MER-SYSTEM VPS  🛡️    "
echo "======================================================="
echo ""
echo "Membangun jalur SSH terenkripsi ke server $IP_VPS..."
echo ""

# Membuka SSH Tunnel di background (Mode -f -N)
ssh -f -N -L ${GRAFANA_PORT}:127.0.0.1:${GRAFANA_PORT} \
          -L ${UPTIME_PORT}:127.0.0.1:${UPTIME_PORT} \
          -L ${NETDATA_PORT}:127.0.0.1:${NETDATA_PORT} \
          -p ${SSH_PORT} \
          ${USER}@${IP_VPS}

if [ $? -eq 0 ]; then
    echo "✅ Tunnel Berhasil Terhubung!"
    echo "-------------------------------------------------------"
    echo "Silakan klik/buka tautan berikut di browser Anda:"
    echo ""
    echo " 📊 Grafana (Logs)        : http://localhost:3000"
    echo " ⏱️  Uptime Kuma (Health)  : http://localhost:3001"
    echo " 📈 Netdata (Server Metrics) : http://localhost:19999"
    echo ""
    echo "Catatan: Koneksi ini berjalan di background."
    echo "Untuk mematikan tunnel, jalankan:"
    echo "  pkill -f \"ssh.*-L 3000\""
    echo "======================================================="
else
    echo "❌ GAGAL terhubung ke VPS."
    echo "Pastikan kunci SSH terbuka atau cek kembali IP Anda."
fi

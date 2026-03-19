#!/bin/bash
echo "Memulai SSH tunnel ke mer-vps pada port 8080..."
echo ""
echo "=========================================================="
echo "Aplikasi Staging akan dapat diakses melalui link berikut:"
echo "👉 http://localhost:8080"
echo "(Silakan klik atau tekan Ctrl+Klik pada link di atas)"
echo "=========================================================="
echo ""

ssh -L 8080:127.0.0.1:8080 mer-vps

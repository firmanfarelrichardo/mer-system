#!/bin/bash
echo "Memulai SSH tunnel ke mer-vps pada port 8080..."
ssh -L 8080:127.0.0.1:8080 mer-vps

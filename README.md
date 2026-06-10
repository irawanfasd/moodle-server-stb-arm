# moodle-server-stb-arm
cd /root/moodle-server-project
cat > README.md << 'EOF'
# 🎓 Moodle Server on STB ARM

![Moodle](https://img.shields.io/badge/Moodle-5.2-orange)
![PHP](https://img.shields.io/badge/PHP-8.3-blue)
![MariaDB](https://img.shields.io/badge/MariaDB-10.11-brown)
![Nginx](https://img.shields.io/badge/Nginx-1.24-green)
![Armbian](https://img.shields.io/badge/Armbian-26.2.1-red)
![Redis](https://img.shields.io/badge/Redis-7.0-red)

Server Moodle 5.2 berjalan di STB HG680P (Amlogic S905X) dengan Armbian Linux. Proyek ini membuktikan bahwa server pembelajaran bisa berjalan di perangkat murah dengan performa optimal.

## 🚀 Fitur Utama

- ✅ **Moodle 5.2** dengan H5P (29 content types)
- ✅ **AI Chatbot** (rules-based + fuzzy matching + self-learning)
- ✅ **Redis Cache** untuk performa maksimal
- ✅ **Cloudflare Turnstile** + reCAPTCHA
- ✅ **Backup otomatis** ke Google Drive
- ✅ **Auto-repair** setiap 15 menit
- ✅ **14 cron jobs** untuk optimasi
- ✅ **7 lapis keamanan** (Firewall, Fail2ban, Honeypot)
- ✅ **Bot Telegram** monitoring harian
- ✅ **Google Analytics** terintegrasi
- ✅ **HSTS Preload** + SSL Grade A+

## 🖥️ Hardware

| Komponen | Spesifikasi |
|----------|-------------|
| **Device** | STB HG680P |
| **CPU** | Amlogic S905X, 4 Core @1.5GHz |
| **RAM** | 2GB DDR3 |
| **eMMC** | 6.5GB |
| **SD Card** | 15GB |

## 📊 Performa

| Metrik | Nilai |
|--------|-------|
| **CPU Load** | 14-24% |
| **RAM Usage** | 47-49% |
| **CPU Temp** | 37-44°C |
| **Uptime** | 6+ hari |
| **Disk Usage** | 63-65% |

## 🛠️ Software Stack

| Software | Versi |
|----------|-------|
| **OS** | Armbian 26.2.1 (Ubuntu Noble) |
| **Kernel** | 6.1.137-ophub |
| **Web Server** | Nginx 1.24.0 |
| **Database** | MariaDB 10.11.14 |
| **PHP** | 8.3.6 |
| **Cache** | Redis 7.0 |
| **Moodle** | 5.2+ |

## 📂 Struktur Repository

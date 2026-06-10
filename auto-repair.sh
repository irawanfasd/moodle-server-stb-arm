#!/bin/bash
LOG="/var/log/auto-repair.log"

echo "$(date): Auto-repair check started" >> $LOG

# 1. Cek dan restart Nginx jika mati
if ! systemctl is-active --quiet nginx; then
    systemctl restart nginx
    echo "$(date): Nginx was down - restarted" >> $LOG
fi

# 2. Cek dan restart MariaDB jika mati
if ! systemctl is-active --quiet mariadb; then
    systemctl restart mariadb
    echo "$(date): MariaDB was down - restarted" >> $LOG
fi

# 3. Cek dan restart PHP-FPM jika mati
if ! systemctl is-active --quiet php8.3-fpm; then
    systemctl restart php8.3-fpm
    echo "$(date): PHP-FPM was down - restarted" >> $LOG
fi

# 4. Cek dan restart Redis jika mati
if ! systemctl is-active --quiet redis-server; then
    systemctl restart redis-server
    echo "$(date): Redis was down - restarted" >> $LOG
fi

# 5. Cek dan restart Cloudflared jika mati
if ! systemctl is-active --quiet cloudflared; then
    systemctl restart cloudflared
    echo "$(date): Cloudflared was down - restarted" >> $LOG
fi

# 6. Cek disk usage - warning jika > 85%
DISK=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK -gt 85 ]; then
    # Bersihkan cache
    rm -rf /var/www/html/moodledata/cache/*
    rm -rf /var/www/html/moodledata/localcache/*
    apt clean
    journalctl --vacuum-time=7d
    echo "$(date): Disk usage ${DISK}% - cleaned cache" >> $LOG
fi

# 7. Cek RAM - restart PHP jika > 80%
RAM=$(free | grep Mem | awk '{print int($3/$2 * 100)}')
if [ $RAM -gt 80 ]; then
    systemctl restart php8.3-fpm
    echo "$(date): RAM usage ${RAM}% - restarted PHP-FPM" >> $LOG
fi

# 8. Cek suhu CPU - warning jika > 65°C
TEMP=$(cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null | awk '{print $1/1000}')
if [ ! -z "$TEMP" ] && [ $(echo "$TEMP > 65" | bc) -eq 1 ]; then
    echo "$(date): CPU Temp ${TEMP}°C - WARNING!" >> $LOG
fi

# 9. Cek koneksi internet - restart cloudflared jika timeout
if ! ping -c 1 8.8.8.8 > /dev/null 2>&1; then
    systemctl restart cloudflared
    echo "$(date): Internet down - restarted cloudflared" >> $LOG
fi

echo "$(date): Auto-repair check completed" >> $LOG

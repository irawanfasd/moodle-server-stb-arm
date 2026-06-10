#!/bin/bash
DATE=$(date +%Y%m%d)
BACKUP_DIR="/mnt/sdcard/backup"

mkdir -p $BACKUP_DIR

echo "📤 Backup database..."
mysqldump -u root moodle_db > $BACKUP_DIR/moodle_db-$DATE.sql

echo "📤 Backup moodledata..."
tar -czf $BACKUP_DIR/moodledata-$DATE.tar.gz /var/www/html/moodledata

# Hapus backup lokal > 3 hari
# Hapus backup > 2 hari (lebih agresif)
find $BACKUP_DIR -name "*.sql" -mtime +1 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +1 -delete
echo "✅ Backup lokal selesai!"

#!/bin/bash
DATE=$(date +%Y%m%d)
BACKUP_DIR="/mnt/sdcard/backup"

mkdir -p $BACKUP_DIR

echo "📤 Backup database..."
mysqldump -u root moodle_db > $BACKUP_DIR/moodle_db-$DATE.sql

echo "📤 Backup moodledata..."
tar -czf $BACKUP_DIR/moodledata-$DATE.tar.gz /var/www/html/moodledata

echo "☁️ Upload ke Google Drive..."
rclone copy $BACKUP_DIR/moodle_db-$DATE.sql gdrive:MoodleBackup
rclone copy $BACKUP_DIR/moodledata-$DATE.tar.gz gdrive:MoodleBackup

# Hapus backup lokal > 3 hari
find $BACKUP_DIR -name "*.sql" -mtime +3 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +3 -delete

echo "✅ Selesai!"

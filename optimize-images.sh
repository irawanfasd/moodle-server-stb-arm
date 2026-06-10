#!/bin/bash
# Optimasi gambar di moodledata
find /var/www/html/moodledata/filedir -type f \( -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" \) -size +200k -exec jpegoptim --max=80 {} \; 2>/dev/null
find /var/www/html/moodledata/filedir -type f -name "*.png" -size +200k -exec optipng -o2 {} \; 2>/dev/null
echo "Optimasi gambar selesai!"

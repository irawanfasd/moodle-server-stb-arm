<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ============================================================
// KONFIGURASI
// ============================================================
$DB_HOST = 'localhost';
$DB_USER = 'moodle_user';
$DB_PASS = 'password_kuat123';
$DB_NAME = 'moodle_db';

// ============================================================
// KONEKSI DATABASE
// ============================================================
$db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($db->connect_error) {
    echo json_encode(['reply' => '❌ Koneksi database gagal.']);
    exit;
}
$db->set_charset('utf8mb4');

// ============================================================
// SESSION - MEMORI PERCAKAPAN
// ============================================================
session_start();
if (!isset($_SESSION['chat_history'])) $_SESSION['chat_history'] = [];
if (!isset($_SESSION['last_topic'])) $_SESSION['last_topic'] = '';

// ============================================================
// INPUT
// ============================================================
$rawMessage  = trim($_POST['message'] ?? '');
$message     = strtolower($rawMessage);
$reply       = '';
$found       = false;

if ($message === '') {
    echo json_encode(['reply' => '❓ Pesan tidak boleh kosong.']);
    exit;
}

// ============================================================
// TABEL PENDUKUNG
// ============================================================
$db->query("CREATE TABLE IF NOT EXISTS mdl_ai_unknown_questions (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(500) UNIQUE,
    count    INT DEFAULT 1,
    asked_at DATETIME
)");

$db->query("CREATE TABLE IF NOT EXISTS mdl_ai_knowledge (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    keywords VARCHAR(500),
    reply    TEXT,
    hits     INT DEFAULT 0,
    created  DATETIME DEFAULT NOW()
)");

// ============================================================
// FUNGSI HELPER
// ============================================================

/**
 * similarEnough() versi upgrade:
 * - Cek substring langsung
 * - Levenshtein per kata
 * - similar_text() untuk fuzzy
 * - Soundex untuk typo fonetik
 */
/**
 * exactMatch: cek apakah keyword ada sebagai kata utuh (bukan bagian kata lain)
 * Contoh: "server" tidak match "siswa" walau mirip soundex
 */
function exactMatch($input, $keyword) {
    // Cek substring biasa dulu
    if (strpos($input, $keyword) === false) return false;
    // Pastikan ini kata utuh (dikelilingi spasi/awal/akhir)
    return (bool) preg_match('/(?:^|\s)' . preg_quote($keyword, '/') . '(?:\s|$)/', $input);
}

function similarEnough($input, $keywords, $threshold = 72) {
    $input = strtolower(preg_replace('/[^\w\s]/u', '', $input));
    $words = array_filter(explode(' ', $input));

    foreach ($keywords as $kw) {
        $kw = strtolower(trim($kw));
        if (empty($kw)) continue;

        // 1. Exact word match (paling prioritas, tidak ada false positive)
        if (exactMatch($input, $kw)) return true;

        // 2. Substring untuk frasa multi-kata (misal: "kursus terbaru")
        if (strpos($kw, ' ') !== false && strpos($input, $kw) !== false) return true;

        // 3. Levenshtein per kata — HANYA untuk kata pendek (≤5 huruf) dan toleransi ketat
        if (strlen($kw) <= 5) {
            foreach ($words as $w) {
                if (strlen($w) >= 3 && levenshtein($w, $kw) <= 1) return true;
            }
        }

        // 4. similar_text per kata — threshold tinggi supaya tidak false positive
        foreach ($words as $w) {
            if (strlen($w) >= 4 && strlen($kw) >= 4) {
                similar_text($w, $kw, $pct);
                if ($pct >= $threshold) return true;
            }
        }
    }
    return false;
}

/**
 * Simpan pertanyaan yang tidak dikenali
 */
function learnNewQuestion($db, $question) {
    $q    = $db->real_escape_string(substr($question, 0, 500));
    $db->query("INSERT INTO mdl_ai_unknown_questions (question, asked_at)
                VALUES ('$q', NOW())
                ON DUPLICATE KEY UPDATE count = count + 1, asked_at = NOW()");
}

/**
 * Cari jawaban dari knowledge base buatan sendiri
 */
function searchKnowledge($db, $message) {
    $r = $db->query("SELECT id, keywords, reply FROM mdl_ai_knowledge");
    while ($row = $r->fetch_assoc()) {
        $kws = array_map('trim', explode(',', strtolower($row['keywords'])));
        if (similarEnough($message, $kws)) {
            $db->query("UPDATE mdl_ai_knowledge SET hits = hits + 1 WHERE id = {$row['id']}");
            return $row['reply'];
        }
    }
    return null;
}

/**
 * Format angka ribuan
 */
function ribuan($n) {
    return number_format($n, 0, ',', '.');
}

// ============================================================
// SIMPAN HISTORI
// ============================================================
$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $rawMessage];
if (count($_SESSION['chat_history']) > 20) {
    array_shift($_SESSION['chat_history']);
}

// ============================================================
// RULE ENGINE
// ============================================================

// --- 0. Mode Ajar (HARUS PALING ATAS — cek prefix dulu sebelum fuzzy matching) ---
// Format: ajar | keywords | jawaban
if (!$found && (strpos($message, 'ajar') === 0 || strpos($message, 'tambah pengetahuan') === 0)) {
    $parts = explode('|', $rawMessage, 3);
    if (count($parts) === 3) {
        $kws  = $db->real_escape_string(trim($parts[1]));
        $ans  = $db->real_escape_string(trim($parts[2]));
        $db->query("INSERT INTO mdl_ai_knowledge (keywords, reply) VALUES ('$kws', '$ans')");
        $reply = "✅ *Pengetahuan Baru Tersimpan!*\n━━━━━━━━━━━━━━━\n"
               . "🔑 Keywords: *{$kws}*\n"
               . "💬 Jawaban: {$ans}\n\n"
               . "Sekarang aku sudah bisa menjawab pertanyaan dengan keyword tersebut! 🧠";
    } else {
        $reply = "📝 *Format Mengajar AI*\n━━━━━━━━━━━━━━━\n"
               . "Gunakan format:\n"
               . "*ajar | keyword1,keyword2 | jawaban kamu*\n\n"
               . "Contoh:\n"
               . "ajar | jadwal,uts,ujian | UTS dilaksanakan 10-15 Juli 2025";
    }
    $_SESSION['last_topic'] = 'ajar';
    $found = true;
}

// --- 1. Sapaan ---
if (!$found && similarEnough($message, ['halo','hai','hello','hi','assalam','pagi','siang','sore','malam','bro','kak','bang','apa kabar','hei','hey'])) {
    $hour = (int)date('H');
    if ($hour < 11)       $greet = 'Selamat pagi 🌤️';
    elseif ($hour < 15)   $greet = 'Selamat siang ☀️';
    elseif ($hour < 18)   $greet = 'Selamat sore 🌅';
    else                  $greet = 'Selamat malam 🌙';

    $totalUser  = $db->query("SELECT COUNT(*) as t FROM mdl_user WHERE deleted=0 AND confirmed=1")->fetch_assoc()['t'];
    $totalKursus = $db->query("SELECT COUNT(*) as t FROM mdl_course WHERE visible=1")->fetch_assoc()['t'];

    $reply = "🤖 {$greet}!\n━━━━━━━━━━━━━━━\n"
           . "Saya AI Assistant Moodle.\n"
           . "📊 Saat ini: *" . ribuan($totalUser) . " mahasiswa* | *" . ribuan($totalKursus) . " kursus*\n"
           . "Ketik *bantuan* untuk lihat semua fitur.";
    $_SESSION['last_topic'] = 'sapaan';
    $found = true;
}

// --- 2. Bantuan / Menu ---
if (!$found && (similarEnough($message, ['bantuan','help','perintah','command','fitur','menu','apa saja','bisa apa']) || strlen($message) < 3)) {
    $reply = "📋 *Menu AI Assistant Moodle*\n━━━━━━━━━━━━━━━\n"
           . "👥 *mahasiswa* — Jumlah mahasiswa\n"
           . "📚 *kursus* — Jumlah & daftar kursus\n"
           . "👨‍🏫 *dosen* — Jumlah pengajar\n"
           . "🕐 *login terakhir* — User yang baru login\n"
           . "🏆 *nilai tertinggi* — Top skor quiz\n"
           . "⭐ *user aktif* — User paling rajin\n"
           . "📊 *statistik* — Aktivitas 7 hari\n"
           . "🆕 *kursus terbaru* — Kursus baru dibuat\n"
           . "🖥️ *server* — Info CPU/RAM/Disk\n"
           . "🧠 *ajar* — Tambah pengetahuan baru\n"
           . "🗑️ *reset* — Hapus memori percakapan\n"
           . "\nContoh: *berapa jumlah mahasiswa?*";
    $_SESSION['last_topic'] = 'bantuan';
    $found = true;
}

// --- 3. Jumlah Mahasiswa ---
if (!$found && similarEnough($message, ['mahasiswa','murid','siswa','user','pengguna','terdaftar','peserta','student'])) {
    $r   = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN lastlogin > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) THEN 1 ELSE 0 END) as aktif FROM mdl_user WHERE deleted=0 AND confirmed=1");
    $row = $r->fetch_assoc();
    $reply = "👥 *Jumlah Mahasiswa*\n━━━━━━━━━━━━━━━\n"
           . "Total terdaftar : *" . ribuan($row['total']) . " orang*\n"
           . "Aktif 30 hari   : *" . ribuan($row['aktif']) . " orang*";
    $_SESSION['last_topic'] = 'mahasiswa';
    $found = true;
}

// --- 4. Kursus Terbaru (HARUS sebelum rule kursus biasa!) ---
if (!$found && similarEnough($message, ['kursus terbaru','kursus baru','recent course','baru dibuat','kelas baru','terbaru'])) {
    $r = $db->query("SELECT fullname, shortname, FROM_UNIXTIME(timecreated, '%d %b %Y') as created FROM mdl_course WHERE visible=1 AND id > 1 ORDER BY timecreated DESC LIMIT 5");
    $reply = "🆕 *Kursus Terbaru*\n━━━━━━━━━━━━━━━\n";
    $no = 1;
    while ($row = $r->fetch_assoc()) {
        $reply .= "{$no}. *{$row['fullname']}*\n   ↳ Dibuat: {$row['created']}\n";
        $no++;
    }
    $_SESSION['last_topic'] = 'kursus_baru';
    $found = true;
}

// --- 5. Jumlah Kursus ---
if (!$found && similarEnough($message, ['kursus','course','kelas','matkul','pelajaran','mapel'])) {
    $r   = $db->query("SELECT COUNT(*) as total FROM mdl_course WHERE visible=1 AND id > 1");
    $row = $r->fetch_assoc();

    $r2 = $db->query("SELECT fullname FROM mdl_course WHERE visible=1 AND id > 1 ORDER BY timecreated DESC LIMIT 3");
    $list = '';
    $i = 1;
    while ($row2 = $r2->fetch_assoc()) {
        $list .= "   {$i}. {$row2['fullname']}\n";
        $i++;
    }

    $reply = "📚 *Jumlah Kursus*\n━━━━━━━━━━━━━━━\n"
           . "Kursus tersedia: *" . ribuan($row['total']) . " kursus*\n"
           . "🆕 Terbaru:\n{$list}";
    $_SESSION['last_topic'] = 'kursus';
    $found = true;
}

// --- 5. Jumlah Dosen/Pengajar ---
if (!$found && similarEnough($message, ['dosen','guru','pengajar','teacher','pengampu','instruktur'])) {
    $r   = $db->query("SELECT COUNT(DISTINCT ra.userid) as total FROM mdl_role_assignments ra JOIN mdl_role r ON r.id = ra.roleid WHERE r.shortname IN ('editingteacher','teacher')");
    $row = $r->fetch_assoc();
    $reply = "👨‍🏫 *Jumlah Pengajar*\n━━━━━━━━━━━━━━━\n"
           . "Total pengajar: *" . ribuan($row['total']) . " orang*";
    $_SESSION['last_topic'] = 'dosen';
    $found = true;
}

// --- 6. Login Terakhir ---
if (!$found && similarEnough($message, ['login','masuk','terakhir','terbaru','online','aktif kapan'])) {
    $r = $db->query("SELECT firstname, lastname, email, FROM_UNIXTIME(lastlogin, '%d %b %Y %H:%i') as wkt FROM mdl_user WHERE lastlogin > 0 AND deleted = 0 ORDER BY lastlogin DESC LIMIT 5");
    $reply = "🕐 *5 Login Terakhir*\n━━━━━━━━━━━━━━━\n";
    $no = 1;
    while ($row = $r->fetch_assoc()) {
        $inisial = strtoupper(substr($row['firstname'],0,1)) . strtoupper(substr($row['lastname'],0,1));
        $reply  .= "{$no}. [{$inisial}] ****\n   ↳ {$row['wkt']}\n";
        $no++;
    }
    $_SESSION['last_topic'] = 'login';
    $found = true;
}

// --- 7. Nilai Tertinggi ---
if (!$found && similarEnough($message, ['nilai','tertinggi','quiz','ujian','skor','grade','ranking','peringkat'])) {
    $r = $db->query("SELECT u.firstname, u.lastname, ROUND(MAX(qg.grade),1) as maxgrade, q.name as quizname
                     FROM mdl_quiz_grades qg
                     JOIN mdl_user u ON u.id = qg.userid
                     JOIN mdl_quiz q ON q.id = qg.quiz
                     GROUP BY qg.userid, qg.quiz
                     ORDER BY maxgrade DESC LIMIT 5");
    if ($r && $r->num_rows > 0) {
        $reply = "🏆 *Top 5 Nilai Tertinggi*\n━━━━━━━━━━━━━━━\n";
        $no = 1;
        while ($row = $r->fetch_assoc()) {
            $inisial = strtoupper(substr($row['firstname'],0,1)) . strtoupper(substr($row['lastname'],0,1));
            $reply  .= "{$no}. [{$inisial}] **** — *{$row['maxgrade']}*\n   ↳ {$row['quizname']}\n";
            $no++;
        }
    } else {
        $reply = "📝 Belum ada data nilai quiz.";
    }
    $_SESSION['last_topic'] = 'nilai';
    $found = true;
}

// --- 8. User Paling Aktif ---
if (!$found && similarEnough($message, ['aktif','rajin','sering','top','terbanyak','paling aktif'])) {
    $r = $db->query("SELECT u.firstname, u.lastname, COUNT(l.id) as actions
                     FROM mdl_logstore_standard_log l
                     JOIN mdl_user u ON u.id = l.userid
                     WHERE l.userid > 1 AND l.timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
                     GROUP BY l.userid
                     ORDER BY actions DESC LIMIT 5");
    if ($r && $r->num_rows > 0) {
        $reply = "⭐ *User Paling Aktif (30 Hari)*\n━━━━━━━━━━━━━━━\n";
        $no = 1;
        while ($row = $r->fetch_assoc()) {
            $inisial = strtoupper(substr($row['firstname'],0,1)) . strtoupper(substr($row['lastname'],0,1));
            $reply  .= "{$no}. [{$inisial}] **** — *" . ribuan($row['actions']) . " aktivitas*\n";
            $no++;
        }
    } else {
        $reply = "📝 Belum ada data aktivitas.";
    }
    $_SESSION['last_topic'] = 'aktif';
    $found = true;
}

// --- 9. Statistik Mingguan ---
if (!$found && similarEnough($message, ['minggu','statistik','stat','mingguan','minggu ini','7 hari','tujuh hari'])) {
    $r   = $db->query("SELECT
        (SELECT COUNT(*) FROM mdl_logstore_standard_log WHERE timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))) as actions,
        (SELECT COUNT(DISTINCT userid) FROM mdl_logstore_standard_log WHERE timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))) as users,
        (SELECT COUNT(*) FROM mdl_user WHERE timecreated > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))) as newusers");
    $row = $r->fetch_assoc();
    $reply = "📊 *Statistik 7 Hari Terakhir*\n━━━━━━━━━━━━━━━\n"
           . "Total aktivitas : *" . ribuan($row['actions']) . "*\n"
           . "User aktif      : *" . ribuan($row['users']) . "*\n"
           . "User baru daftar: *" . ribuan($row['newusers']) . "*";
    $_SESSION['last_topic'] = 'statistik';
    $found = true;
}

// --- 11. Info Server ---
// Gunakan exactMatch untuk kata kunci pendek yang rawan false positive
if (!$found && (
    exactMatch($message, 'server') ||
    exactMatch($message, 'cpu') ||
    exactMatch($message, 'ram') ||
    exactMatch($message, 'disk') ||
    exactMatch($message, 'suhu') ||
    exactMatch($message, 'spek') ||
    similarEnough($message, ['memori','penyimpanan','hardware','info server','cek server','status server'])
)) {
    $cpu    = trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2}' 2>/dev/null") ?? '?');
    $ram    = trim(shell_exec("free -h | grep Mem | awk '{print $3\"/\"$2}' 2>/dev/null") ?? '?');
    $disk   = trim(shell_exec("df -h / | tail -1 | awk '{print $3\"/\"$2\" (\"$5\")\"}'  2>/dev/null") ?? '?');
    $tempRaw = trim(shell_exec("cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null") ?? '0');
    $temp   = $tempRaw ? round($tempRaw / 1000, 1) . '°C' : 'N/A';
    $uptime = trim(shell_exec("uptime -p 2>/dev/null") ?? '?');
    $load   = trim(shell_exec("cat /proc/loadavg | awk '{print $1\" \"$2\" \"$3}' 2>/dev/null") ?? '?');

    $reply = "🖥️ *Info Server*\n━━━━━━━━━━━━━━━\n"
           . "CPU Usage : {$cpu}%\n"
           . "RAM       : {$ram}\n"
           . "Disk      : {$disk}\n"
           . "Suhu      : {$temp}\n"
           . "Load Avg  : {$load}\n"
           . "Uptime    : {$uptime}";
    $_SESSION['last_topic'] = 'server';
    $found = true;
}

// --- 13. Reset Memori ---
if (!$found && similarEnough($message, ['reset','hapus memori','lupa','mulai baru','clear','bersihkan'])) {
    $_SESSION['chat_history'] = [];
    $_SESSION['last_topic']   = '';
    $reply = "🗑️ Memori percakapan telah direset. Halo lagi! Ketik *bantuan* untuk mulai.";
    $found = true;
}

// --- 14. Knowledge Base (Pengetahuan yang diajarkan) ---
if (!$found) {
    $learned = searchKnowledge($db, $message);
    if ($learned) {
        $reply = "💡 " . $learned;
        $found = true;
    }
}

// --- 15. Konteks Lanjutan ---
// Jika user nanya "lebih lanjut" / "lagi" sesuai topik sebelumnya
if (!$found && similarEnough($message, ['lagi','lebih','detail','lanjut','info lagi','tambah'])) {
    $topik = $_SESSION['last_topic'] ?? '';
    if ($topik) {
        $reply = "ℹ️ Mau info lebih lanjut tentang *{$topik}*?\nCoba tanya lebih spesifik, misalnya: *nilai tertinggi kursus apa?*";
        $found = true;
    }
}

// --- 16. Tidak Dikenal → Simpan ke DB ---
if (!$found) {
    learnNewQuestion($db, $rawMessage);
    $unknownCount = $db->query("SELECT COUNT(*) as c FROM mdl_ai_unknown_questions")->fetch_assoc()['c'];
    $reply = "🤔 *Belum Bisa Menjawab*\n━━━━━━━━━━━━━━━\n"
           . "Pertanyaan kamu sudah dicatat.\n"
           . "📋 Total pertanyaan belum terjawab: *{$unknownCount}*\n"
           . "💡 Kamu bisa ajarkan aku dengan perintah:\n"
           . "*ajar | keyword | jawaban*\n"
           . "Atau ketik *bantuan* untuk lihat fitur.";
}

// ============================================================
// SIMPAN BALASAN KE HISTORI
// ============================================================
$_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $reply];

// ============================================================
// OUTPUT
// ============================================================
echo json_encode([
    'reply'   => $reply,
    'topic'   => $_SESSION['last_topic'] ?? '',
    'history' => count($_SESSION['chat_history'])
], JSON_UNESCAPED_UNICODE);

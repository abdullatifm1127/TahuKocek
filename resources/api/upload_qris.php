<?php
/**
 * api/upload_qris.php – Upload foto kode QRIS toko
 *
 * POST multipart/form-data
 *   qris_foto : file gambar (JPG/PNG/WEBP)
 *
 * Response: { success: true, url: "/TahuKocek/uploads/qris_xxx.png" }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Hanya menerima POST.'], 405);
}

/* ── Auto-migration: tambah kolom qris_url jika belum ada ── */
try {
    $pdo = db();
    $cols = $pdo->query("SHOW COLUMNS FROM toko LIKE 'qris_url'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE toko ADD COLUMN qris_url VARCHAR(255) NOT NULL DEFAULT '' AFTER metode_qris");
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Migration gagal: ' . $e->getMessage()], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Hanya menerima POST.'], 405);
}

if (empty($_FILES['qris_foto'])) {
    jsonResponse(['success' => false, 'message' => 'Tidak ada file yang dikirim.'], 400);
}

$file = $_FILES['qris_foto'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMsg = [
        UPLOAD_ERR_INI_SIZE   => 'File melebihi upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'File melebihi MAX_FILE_SIZE.',
        UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file dipilih.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file.',
    ];
    jsonResponse(['success' => false, 'message' => $errMsg[$file['error']] ?? 'Upload gagal.'], 400);
}

/* Validasi MIME */
$allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$finfo       = new finfo(FILEINFO_MIME_TYPE);
$mimeType    = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowedMime)) {
    jsonResponse(['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, atau WEBP.'], 415);
}

/* Validasi ukuran (maks. 3 MB) */
if ($file['size'] > 3 * 1024 * 1024) {
    jsonResponse(['success' => false, 'message' => 'Ukuran file melebihi 3 MB.'], 413);
}

/* Nama file unik */
$extMap   = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$ext      = $extMap[$mimeType] ?? 'png';
$filename = 'qris_' . bin2hex(random_bytes(8)) . '.' . $ext;

$uploadDir = dirname(__DIR__, 2) . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

/* Hapus file QRIS lama */
try {
    $pdo  = db();
    $old  = $pdo->query('SELECT qris_url FROM toko LIMIT 1')->fetchColumn();
    if ($old) {
        $oldPath = dirname(__DIR__, 2) . '/' . ltrim($old, '/');
        if (file_exists($oldPath)) @unlink($oldPath);
    }
} catch (Exception $e) { /* lanjut */ }

$destPath = $uploadDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file ke server.'], 500);
}

$url = '/TahuKocek/uploads/' . $filename;

/* Simpan URL ke tabel toko */
try {
    $pdo = db();
    $pdo->prepare('UPDATE toko SET qris_url = ? WHERE id = 1')->execute([$url]);
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'File tersimpan tapi DB gagal: ' . $e->getMessage(), 'url' => $url], 500);
}

jsonResponse(['success' => true, 'message' => 'Foto QRIS berhasil diupload.', 'url' => $url]);

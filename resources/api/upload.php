<?php
/**
 * api/upload.php – Upload foto varian
 *
 * POST multipart/form-data
 *   foto     : file gambar (JPG/PNG/WEBP/GIF)
 *   varian_id: INT (opsional) — jika dikirim, langsung update foto_url di tabel varian
 *
 * Response JSON:
 *   { success: true, url: "/TahuKocek/uploads/varian_xxx.jpg" }
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

if (empty($_FILES['foto'])) {
    jsonResponse(['success' => false, 'message' => 'Tidak ada file yang dikirim.'], 400);
}

$file      = $_FILES['foto'];
$varianId  = isset($_POST['varian_id']) ? (int)$_POST['varian_id'] : 0;

/* ── Validasi error upload ── */
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMsg = [
        UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload_max_filesize di php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'File melebihi MAX_FILE_SIZE di form.',
        UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara tidak ditemukan.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file.',
    ];
    $msg = $errMsg[$file['error']] ?? 'Upload gagal (kode: '.$file['error'].')';
    jsonResponse(['success' => false, 'message' => $msg], 400);
}

/* ── Validasi tipe file via MIME ── */
$allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
$finfo       = new finfo(FILEINFO_MIME_TYPE);
$mimeType    = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMime)) {
    jsonResponse(['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, WEBP, atau GIF.'], 415);
}

/* ── Validasi ukuran (maks. 5 MB) ── */
if ($file['size'] > 5 * 1024 * 1024) {
    jsonResponse(['success' => false, 'message' => 'Ukuran file melebihi batas 5 MB.'], 413);
}

/* ── Tentukan ekstensi & nama file unik ── */
$extMap  = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif'];
$ext     = $extMap[$mimeType] ?? 'jpg';
$prefix  = $varianId > 0 ? 'varian_' . $varianId : 'varian';
$filename= $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

$uploadDir = dirname(__DIR__, 2) . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $filename;

/* ── Hapus foto lama jika ada (update) ── */
if ($varianId > 0) {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT foto_url FROM varian WHERE id = ?');
        $stmt->execute([$varianId]);
        $row  = $stmt->fetch();
        if ($row && !empty($row['foto_url'])) {
            $oldFile = dirname(__DIR__, 2) . '/' . ltrim($row['foto_url'], '/');
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }
    } catch (PDOException $e) {
        // Lanjut meski gagal hapus lama
    }
}

/* ── Pindahkan file ── */
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan file ke server.'], 500);
}

/* ── URL relatif untuk disimpan ke DB & dikembalikan ke client ── */
$relativeUrl = '/TahuKocek/uploads/' . $filename;

/* ── Update foto_url di database jika varian_id dikirim ── */
if ($varianId > 0) {
    try {
        $pdo = db();
        $pdo->prepare('UPDATE varian SET foto_url = ? WHERE id = ?')
            ->execute([$relativeUrl, $varianId]);
    } catch (PDOException $e) {
        // File sudah tersimpan, tapi DB gagal — kembalikan URL agar client bisa retry
        jsonResponse([
            'success' => false,
            'message' => 'File tersimpan tapi gagal update database: ' . $e->getMessage(),
            'url'     => $relativeUrl,
        ], 500);
    }
}

jsonResponse([
    'success' => true,
    'message' => 'Foto berhasil diupload.',
    'url'     => $relativeUrl,
    'filename'=> $filename,
]);

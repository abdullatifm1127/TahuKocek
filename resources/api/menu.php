<?php
/**
 * api/menu.php – Daftar varian aktif untuk halaman user
 *
 * GET /api/menu.php           → semua varian aktif
 * GET /api/menu.php?level=2   → filter by level pedas (1–3)
 *
 * Tidak butuh login (halaman publik).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';

try {
    $pdo    = db();
    $where  = ["status = 'aktif'"];
    $params = [];

    $sql  = 'SELECT id, kode, nama, deskripsi, harga, stok, emoji, foto_url, status
             FROM varian
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Tambahkan base URL agar front-end bisa langsung pakai
    foreach ($rows as &$r) {
        // foto_url sudah berupa path relatif seperti /TahuKocek/uploads/xxx.jpg
        // Biarkan apa adanya; front-end langsung pakai sebagai src
        $r['harga_fmt'] = 'Rp' . number_format($r['harga'], 0, ',', '.');
    }
    unset($r);

    jsonResponse(['success' => true, 'data' => $rows]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Gagal memuat menu: ' . $e->getMessage()], 500);
}

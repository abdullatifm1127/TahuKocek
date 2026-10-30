<?php
/**
 * api/varian.php – CRUD Varian
 *
 * GET    /api/varian.php          → list semua varian
 * GET    /api/varian.php?id=1     → detail satu varian
 * POST   /api/varian.php          → tambah varian baru
 * PUT    /api/varian.php?id=1     → update varian
 * DELETE /api/varian.php?id=1     → hapus varian
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $pdo = db();

    /* ── GET ─────────────────────────────────────────── */
    if ($method === 'GET') {
        if ($id) {
            $s = $pdo->prepare('SELECT * FROM varian WHERE id = ?');
            $s->execute([$id]);
            $row = $s->fetch();
            if (!$row) jsonResponse(['success'=>false,'message'=>'Varian tidak ditemukan.'], 404);
            jsonResponse(['success'=>true,'data'=>$row]);
        }

        $rows = $pdo->query('SELECT * FROM varian ORDER BY id ASC')->fetchAll();
        jsonResponse(['success'=>true,'data'=>$rows]);
    }

    /* ── POST (create) ───────────────────────────────── */
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $kode   = trim($body['kode']   ?? '');
        $nama   = trim($body['nama']   ?? '');
        $desc   = trim($body['deskripsi'] ?? '');
        $harga  = (int)($body['harga'] ?? 0);
        $stok   = (int)($body['stok']  ?? 0);
        $emoji  = trim($body['emoji']  ?? '🧆');
        $sku    = trim($body['sku']    ?? '1 porsi / 10 pcs');
        $foto   = trim($body['foto_url'] ?? '');
        $status = in_array($body['status'] ?? '', ['aktif','nonaktif']) ? $body['status'] : 'aktif';

        if (!$nama) jsonResponse(['success'=>false,'message'=>'Nama varian wajib diisi.'], 422);
        if ($harga < 0) jsonResponse(['success'=>false,'message'=>'Harga tidak valid.'], 422);
        if ($stok  < 0) jsonResponse(['success'=>false,'message'=>'Stok tidak valid.'],  422);

        // Auto-generate kode jika kosong
        if (!$kode) {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nama), 0, 3));
            $kode   = 'TK-' . $prefix . '-' . time();
        }

        // Cek kode unik
        $ck = $pdo->prepare('SELECT id FROM varian WHERE kode = ?');
        $ck->execute([$kode]);
        if ($ck->fetch()) jsonResponse(['success'=>false,'message'=>'Kode varian sudah digunakan.'], 409);

        $s = $pdo->prepare(
            'INSERT INTO varian (kode,nama,deskripsi,harga,stok,emoji,sku,foto_url,status)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $s->execute([$kode,$nama,$desc,$harga,$stok,$emoji,$sku,$foto,$status]);
        $newId = (int)$pdo->lastInsertId();

        $row = $pdo->prepare('SELECT * FROM varian WHERE id = ?');
        $row->execute([$newId]);
        jsonResponse(['success'=>true,'message'=>'Varian berhasil ditambahkan.','data'=>$row->fetch()], 201);
    }

    /* ── PUT (update) ────────────────────────────────── */
    if ($method === 'PUT') {
        if (!$id) jsonResponse(['success'=>false,'message'=>'ID diperlukan.'], 400);

        $chk = $pdo->prepare('SELECT id FROM varian WHERE id = ?');
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonResponse(['success'=>false,'message'=>'Varian tidak ditemukan.'], 404);

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = []; $params = [];

        if (isset($body['nama']))      { $fields[] = 'nama = ?';      $params[] = trim($body['nama']); }
        if (isset($body['deskripsi'])) { $fields[] = 'deskripsi = ?'; $params[] = trim($body['deskripsi']); }
        if (isset($body['harga']))     { $fields[] = 'harga = ?';     $params[] = (int)$body['harga']; }
        if (isset($body['stok']))      { $fields[] = 'stok = ?';      $params[] = (int)$body['stok']; }
        if (isset($body['emoji']))     { $fields[] = 'emoji = ?';     $params[] = trim($body['emoji']); }
        if (isset($body['foto_url']))  { $fields[] = 'foto_url = ?';  $params[] = trim($body['foto_url']); }
        if (isset($body['status']) && in_array($body['status'],['aktif','nonaktif'])) {
            $fields[] = 'status = ?'; $params[] = $body['status'];
        }

        if (!$fields) jsonResponse(['success'=>false,'message'=>'Tidak ada data yang diubah.'], 400);

        $params[] = $id;
        $pdo->prepare('UPDATE varian SET ' . implode(', ', $fields) . ' WHERE id = ?')
            ->execute($params);

        $row = $pdo->prepare('SELECT * FROM varian WHERE id = ?');
        $row->execute([$id]);
        jsonResponse(['success'=>true,'message'=>'Varian berhasil diperbarui.','data'=>$row->fetch()]);
    }

    /* ── DELETE ──────────────────────────────────────── */
    if ($method === 'DELETE') {
        if (!$id) jsonResponse(['success'=>false,'message'=>'ID diperlukan.'], 400);

        // Cek apakah varian digunakan di item_pesanan
        $used = $pdo->prepare('SELECT COUNT(*) FROM item_pesanan WHERE varian_id = ?');
        $used->execute([$id]);
        if ((int)$used->fetchColumn() > 0) {
            // Nonaktifkan saja agar data historis tetap valid
            $pdo->prepare('UPDATE varian SET status = ? WHERE id = ?')
                ->execute(['nonaktif', $id]);
            jsonResponse(['success'=>true,'message'=>'dinonaktifkan','info'=>'Varian memiliki riwayat pesanan sehingga dinonaktifkan, bukan dihapus permanen.']);
        }

        $pdo->prepare('DELETE FROM varian WHERE id = ?')->execute([$id]);
        jsonResponse(['success'=>true,'message'=>'dihapus']);
    }

    jsonResponse(['success'=>false,'message'=>'Metode tidak diizinkan.'], 405);

} catch (PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$e->getMessage()], 500);
}

<?php
/**
 * api/pesanan.php – CRUD Pesanan
 *
 * GET    /api/pesanan.php              → list pesanan (filter: ?status=baru&q=TK-1064)
 * GET    /api/pesanan.php?id=1         → detail pesanan + item
 * POST   /api/pesanan.php              → buat pesanan baru (dari user)
 * PUT    /api/pesanan.php?id=1         → update status pesanan (admin)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

try {
    $pdo = db();

    /* ── GET list / detail ───────────────────────────── */
    if ($method === 'GET') {
        if ($id) {
            // Detail pesanan + item
            $s = $pdo->prepare('SELECT * FROM pesanan WHERE id = ?');
            $s->execute([$id]);
            $pesanan = $s->fetch();
            if (!$pesanan) jsonResponse(['success'=>false,'message'=>'Pesanan tidak ditemukan.'], 404);

            $items = $pdo->prepare(
                'SELECT ip.*, v.emoji FROM item_pesanan ip
                 LEFT JOIN varian v ON v.id = ip.varian_id
                 WHERE ip.pesanan_id = ?'
            );
            $items->execute([$id]);
            $pesanan['items'] = $items->fetchAll();
            jsonResponse(['success'=>true,'data'=>$pesanan]);
        }

        // List dengan filter opsional
        $where  = ['1=1']; $params = [];

        if (!$isAdmin) {
            // User hanya lihat pesanannya sendiri
            $where[] = 'p.user_id = ?';
            $params[] = (int)$_SESSION['user_id'];
        }

        if (!empty($_GET['status'])) {
            $where[] = 'p.status = ?';
            $params[] = $_GET['status'];
        }

        if (!empty($_GET['q'])) {
            $where[] = '(p.nomor LIKE ? OR p.nama_pelanggan LIKE ?)';
            $q = '%' . $_GET['q'] . '%';
            $params[] = $q; $params[] = $q;
        }

        $sql  = 'SELECT p.*, COUNT(ip.id) AS jumlah_item
                 FROM pesanan p
                 LEFT JOIN item_pesanan ip ON ip.pesanan_id = p.id
                 WHERE ' . implode(' AND ', $where) . '
                 GROUP BY p.id
                 ORDER BY p.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Statistik ringkas untuk admin
        $stats = null;
        if ($isAdmin) {
            $today  = date('Y-m-d');
            $st = $pdo->prepare(
                "SELECT
                   COUNT(*)                                    AS total_hari_ini,
                   SUM(CASE WHEN status IN('baru','diproses') THEN 1 ELSE 0 END) AS perlu_diproses,
                   SUM(CASE WHEN status = 'siap'    THEN 1 ELSE 0 END)           AS siap_diambil,
                   SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END)           AS selesai,
                   COALESCE(SUM(CASE WHEN status='selesai' THEN total ELSE 0 END),0) AS penjualan
                 FROM pesanan
                 WHERE DATE(created_at) = ?"
            );
            $st->execute([$today]);
            $stats = $st->fetch();
        }

        jsonResponse(['success'=>true,'data'=>$rows,'stats'=>$stats]);
    }

    /* ── POST (buat pesanan baru) ────────────────────── */
    if ($method === 'POST') {
        $body          = json_decode(file_get_contents('php://input'), true) ?? [];
        $nama          = trim($body['nama_pelanggan']  ?? '');
        $phone         = trim($body['phone_pelanggan'] ?? '');
        $metode_ambil  = in_array($body['metode_ambil'] ?? '', ['ambil','antar']) ? $body['metode_ambil'] : 'ambil';
        $metode_bayar  = in_array($body['metode_bayar'] ?? '', ['QRIS','Tunai','Transfer bank']) ? $body['metode_bayar'] : 'QRIS';
        $catatan       = trim($body['catatan'] ?? '');
        $items         = $body['items'] ?? [];

        if (!$nama)       jsonResponse(['success'=>false,'message'=>'Nama pelanggan wajib diisi.'], 422);
        if (empty($items)) jsonResponse(['success'=>false,'message'=>'Pesanan tidak boleh kosong.'], 422);

        // Hitung subtotal + validasi stok
        $subtotal = 0;
        $enriched = [];
        foreach ($items as $item) {
            $vid = (int)($item['varian_id'] ?? 0);
            $qty = (int)($item['qty']       ?? 0);
            if ($vid <= 0 || $qty <= 0) continue;

            $v = $pdo->prepare('SELECT id, nama, harga, stok, status FROM varian WHERE id = ?');
            $v->execute([$vid]);
            $varian = $v->fetch();
            if (!$varian) jsonResponse(['success'=>false,'message'=>"Varian ID $vid tidak ditemukan."], 422);
            if ($varian['status'] !== 'aktif') jsonResponse(['success'=>false,'message'=>"Varian {$varian['nama']} tidak aktif."], 422);
            if ($varian['stok'] < $qty) jsonResponse(['success'=>false,'message'=>"Stok {$varian['nama']} tidak mencukupi (tersisa {$varian['stok']})."], 422);

            $enriched[] = ['varian'=>$varian,'qty'=>$qty,'sub'=>$varian['harga']*$qty];
            $subtotal  += $varian['harga'] * $qty;
        }

        if (empty($enriched)) jsonResponse(['success'=>false,'message'=>'Tidak ada item valid.'], 422);

        $biaya_tambahan = 1000; // service fee tetap
        $total          = $subtotal + $biaya_tambahan;
        $userId         = $_SESSION['user_id'] ?? null;

        // Generate nomor pesanan
        $lastNo = $pdo->query("SELECT MAX(CAST(SUBSTRING(nomor,4) AS UNSIGNED)) FROM pesanan")->fetchColumn();
        $nomor  = 'TK-' . str_pad(($lastNo ?? 1000) + 1, 4, '0', STR_PAD_LEFT);

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO pesanan (nomor,user_id,nama_pelanggan,phone_pelanggan,
                  metode_ambil,metode_bayar,status_bayar,status,subtotal,biaya_tambahan,total,catatan)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $nomor, $userId, $nama, $phone,
                $metode_ambil, $metode_bayar, 'Belum bayar', 'baru',
                $subtotal, $biaya_tambahan, $total, $catatan
            ]);
            $pesananId = (int)$pdo->lastInsertId();

            foreach ($enriched as $e) {
                $pdo->prepare(
                    'INSERT INTO item_pesanan (pesanan_id,varian_id,nama_varian,harga_satuan,qty,subtotal)
                     VALUES (?,?,?,?,?,?)'
                )->execute([$pesananId, $e['varian']['id'], $e['varian']['nama'], $e['varian']['harga'], $e['qty'], $e['sub']]);

                // Kurangi stok
                $pdo->prepare('UPDATE varian SET stok = stok - ? WHERE id = ?')
                    ->execute([$e['qty'], $e['varian']['id']]);
            }

            $pdo->commit();
            jsonResponse(['success'=>true,'message'=>'Pesanan berhasil dibuat.','nomor'=>$nomor,'pesanan_id'=>$pesananId], 201);

        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    /* ── PUT (update status) ─────────────────────────── */
    if ($method === 'PUT') {
        requireAdmin();
        if (!$id) jsonResponse(['success'=>false,'message'=>'ID diperlukan.'], 400);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = []; $params = [];

        $validStatus = ['baru','diproses','siap','selesai','batal'];
        if (isset($body['status']) && in_array($body['status'], $validStatus)) {
            $fields[] = 'status = ?'; $params[] = $body['status'];
        }

        $validBayar = ['Lunas','Belum bayar','Dibatalkan'];
        if (isset($body['status_bayar']) && in_array($body['status_bayar'], $validBayar)) {
            $fields[] = 'status_bayar = ?'; $params[] = $body['status_bayar'];
        }

        if (!$fields) jsonResponse(['success'=>false,'message'=>'Tidak ada field yang diubah.'], 400);

        $params[] = $id;
        $pdo->prepare('UPDATE pesanan SET '.implode(', ',$fields).' WHERE id = ?')->execute($params);

        $row = $pdo->prepare('SELECT * FROM pesanan WHERE id = ?');
        $row->execute([$id]);
        jsonResponse(['success'=>true,'message'=>'Pesanan diperbarui.','data'=>$row->fetch()]);
    }

    jsonResponse(['success'=>false,'message'=>'Metode tidak diizinkan.'], 405);

} catch (PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$e->getMessage()], 500);
}

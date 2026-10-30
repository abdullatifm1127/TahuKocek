<?php
/**
 * api/setting.php – Pengaturan toko & jam operasional
 *
 * GET /api/setting.php              → ambil semua pengaturan
 * PUT /api/setting.php              → update pengaturan toko
 * PUT /api/setting.php?section=jam  → update jam operasional
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';

// GET boleh diakses user biasa (untuk load data toko & QRIS)
// PUT hanya admin (diblokir per-method di bawah)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireAdmin();
} else {
    requireLogin();
}

$method  = $_SERVER['REQUEST_METHOD'];
$section = $_GET['section'] ?? 'toko';

try {
    $pdo = db();

    /* ── Auto-migration: kolom qris_url ── */
    $hasQris = $pdo->query("SHOW COLUMNS FROM toko LIKE 'qris_url'")->fetchAll();
    if (empty($hasQris)) {
        $pdo->exec("ALTER TABLE toko ADD COLUMN qris_url VARCHAR(255) NOT NULL DEFAULT '' AFTER metode_qris");
    }

    /* ── GET ─────────────────────────────────────────── */
    if ($method === 'GET') {
        $toko = $pdo->query('SELECT * FROM toko LIMIT 1')->fetch();
        $jam  = $pdo->query('SELECT * FROM jam_operasional ORDER BY FIELD(hari,"Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu")')->fetchAll();
        jsonResponse(['success'=>true,'toko'=>$toko,'jam'=>$jam]);
    }

    /* ── PUT toko ────────────────────────────────────── */
    if ($method === 'PUT' && $section !== 'jam') {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = []; $params = [];

        $cols = [
            'nama_toko','nama_pemilik','slogan','deskripsi','whatsapp','email',
            'alamat','kota','provinsi','kode_pos','logo_url','qris_url',
        ];
        foreach ($cols as $col) {
            if (array_key_exists($col, $body)) {
                $fields[] = "$col = ?";
                $params[] = trim($body[$col]);
            }
        }

        $bools = [
            'terima_pesanan','ambil_di_toko','antar_lokal',
            'metode_qris','metode_tunai','metode_transfer',
            'notif_whatsapp','notif_email','notif_bunyi',
        ];
        foreach ($bools as $col) {
            if (array_key_exists($col, $body)) {
                $fields[] = "$col = ?";
                $params[] = $body[$col] ? 1 : 0;
            }
        }

        $ints = ['estimasi_menit_min','estimasi_menit_max','minimum_pesanan'];
        foreach ($ints as $col) {
            if (array_key_exists($col, $body)) {
                $fields[] = "$col = ?";
                $params[] = (int)$body[$col];
            }
        }

        if (!$fields) jsonResponse(['success'=>false,'message'=>'Tidak ada data yang diubah.'], 400);

        $pdo->prepare('UPDATE toko SET ' . implode(', ', $fields) . ' WHERE id = 1')
            ->execute($params);

        $toko = $pdo->query('SELECT * FROM toko LIMIT 1')->fetch();
        jsonResponse(['success'=>true,'message'=>'Pengaturan toko berhasil disimpan.','toko'=>$toko]);
    }

    /* ── PUT jam operasional ─────────────────────────── */
    if ($method === 'PUT' && $section === 'jam') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        // body: [ { hari:"Senin", aktif:1, buka:"10:00", tutup:"21:00" }, ... ]

        if (empty($body)) jsonResponse(['success'=>false,'message'=>'Data jam kosong.'], 400);

        $validHari = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
        foreach ($body as $row) {
            $hari  = $row['hari']  ?? '';
            $aktif = isset($row['aktif']) ? ($row['aktif'] ? 1 : 0) : 1;
            $buka  = $row['buka']  ?? '10:00';
            $tutup = $row['tutup'] ?? '21:00';

            if (!in_array($hari, $validHari)) continue;

            // Validasi format waktu HH:MM
            if (!preg_match('/^\d{2}:\d{2}$/', $buka))  $buka  = '10:00';
            if (!preg_match('/^\d{2}:\d{2}$/', $tutup)) $tutup = '21:00';

            $pdo->prepare('UPDATE jam_operasional SET aktif=?, buka=?, tutup=? WHERE hari=?')
                ->execute([$aktif, $buka.':00', $tutup.':00', $hari]);
        }

        $jam = $pdo->query('SELECT * FROM jam_operasional ORDER BY FIELD(hari,"Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu")')->fetchAll();
        jsonResponse(['success'=>true,'message'=>'Jam operasional berhasil disimpan.','jam'=>$jam]);
    }

    jsonResponse(['success'=>false,'message'=>'Metode tidak diizinkan.'], 405);

} catch (PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$e->getMessage()], 500);
}

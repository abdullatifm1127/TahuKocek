<?php
/**
 * api/laporan.php – Data laporan dari database
 *
 * GET /api/laporan.php?periode=7hari   → ringkasan 7 hari terakhir
 * GET /api/laporan.php?periode=hari    → hari ini
 * GET /api/laporan.php?periode=bulan   → bulan ini
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db.php';
requireAdmin();

$periode = $_GET['periode'] ?? '7hari';

try {
    $pdo = db();

    // Tentukan rentang tanggal
    switch ($periode) {
        case 'hari':
            $dari  = date('Y-m-d 00:00:00');
            $ke    = date('Y-m-d 23:59:59');
            $label = 'Hari ini · ' . date('d M Y');
            break;
        case 'bulan':
            $dari  = date('Y-m-01 00:00:00');
            $ke    = date('Y-m-t 23:59:59');
            $label = 'Bulan ini · ' . date('F Y');
            break;
        default: // 7hari
            $dari  = date('Y-m-d 00:00:00', strtotime('-6 days'));
            $ke    = date('Y-m-d 23:59:59');
            $label = date('d M', strtotime('-6 days')) . ' – ' . date('d M Y');
    }

    // ── Statistik utama ──────────────────────────────────
    $stat = $pdo->prepare(
        "SELECT
           COALESCE(SUM(CASE WHEN status='selesai' THEN total    ELSE 0 END), 0) AS pendapatan,
           COUNT(*)                                                               AS total_transaksi,
           COALESCE(AVG(CASE WHEN status='selesai' THEN total    END), 0)        AS rata_rata,
           COALESCE(SUM(CASE WHEN status='selesai' THEN (
             SELECT SUM(qty) FROM item_pesanan ip WHERE ip.pesanan_id = p.id
           ) ELSE 0 END), 0)                                                     AS total_porsi
         FROM pesanan p
         WHERE created_at BETWEEN ? AND ? AND status != 'batal'"
    );
    $stat->execute([$dari, $ke]);
    $summary = $stat->fetch();

    // ── Tren harian ──────────────────────────────────────
    $tren = $pdo->prepare(
        "SELECT
           DATE(created_at)          AS tanggal,
           COALESCE(SUM(CASE WHEN status='selesai' THEN total ELSE 0 END), 0) AS pendapatan,
           COUNT(*)                  AS transaksi
         FROM pesanan
         WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY tanggal ASC"
    );
    $tren->execute([$dari, $ke]);
    $trenData = $tren->fetchAll();

    // ── Metode pembayaran ────────────────────────────────
    $bayar = $pdo->prepare(
        "SELECT metode_bayar,
                COUNT(*)       AS jumlah,
                SUM(total)     AS total_nominal
         FROM pesanan
         WHERE created_at BETWEEN ? AND ? AND status_bayar = 'Lunas'
         GROUP BY metode_bayar
         ORDER BY jumlah DESC"
    );
    $bayar->execute([$dari, $ke]);
    $metodeBayar = $bayar->fetchAll();

    // ── Performa varian ──────────────────────────────────
    $varian = $pdo->prepare(
        "SELECT
           v.id, v.kode, v.nama, v.harga AS harga_per_porsi, v.emoji,
           COALESCE(SUM(ip.qty), 0)      AS porsi_terjual,
           COALESCE(SUM(ip.subtotal), 0) AS pendapatan
         FROM varian v
         LEFT JOIN item_pesanan ip ON ip.varian_id = v.id
         LEFT JOIN pesanan p       ON p.id = ip.pesanan_id
                                  AND p.created_at BETWEEN ? AND ?
                                  AND p.status = 'selesai'
         WHERE v.status = 'aktif'
         GROUP BY v.id
         ORDER BY pendapatan DESC"
    );
    $varian->execute([$dari, $ke]);
    $varianData = $varian->fetchAll();

    // Hitung kontribusi %
    $totalPendapatanVarian = array_sum(array_column($varianData, 'pendapatan'));
    foreach ($varianData as &$v) {
        $v['kontribusi'] = $totalPendapatanVarian > 0
            ? round($v['pendapatan'] / $totalPendapatanVarian * 100)
            : 0;
    }
    unset($v);

    jsonResponse([
        'success'      => true,
        'periode'      => $label,
        'dari'         => $dari,
        'ke'           => $ke,
        'summary'      => $summary,
        'tren'         => $trenData,
        'metode_bayar' => $metodeBayar,
        'varian'       => $varianData,
    ]);

} catch (PDOException $e) {
    jsonResponse(['success'=>false,'message'=>'Database error: '.$e->getMessage()], 500);
}

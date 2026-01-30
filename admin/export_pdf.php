<?php
require_once '../vendor/autoload.php'; // Path ke autoload dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
require "../config/database.php";

// ... (Ambil data dengan query yang sama seperti export_excel.php di atas) ...
// Asumsikan data hasil fetch sudah ada di variabel $data

$html = '
<style>
    table { width: 100%; border-collapse: collapse; font-family: sans-serif; font-size: 12px; }
    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
    th { background-color: #eee; }
    h2 { text-align: center; font-family: sans-serif; }
</style>
<h2>Laporan Arsip Peminjaman ' . ucfirst($activeTab) . '</h2>
<table>
    <thead>
        <tr>
            <th>Peminjam</th>
            <th>Prodi</th>
            <th>Aset</th>
            <th>Tanggal</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>';

foreach ($data as $row) {
    $html .= '<tr>
        <td>' . $row['user_nama'] . '</td>
        <td>' . $row['prodi'] . '</td>
        <td>' . $row['nama_aset'] . '</td>
        <td>' . $row['tanggal_mulai'] . '</td>
        <td>' . $row['status'] . '</td>
    </tr>';
}

$html .= '</tbody></table>';

// Inisialisasi Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Laporan_Arsip_" . $activeTab . ".pdf", ["Attachment" => true]);
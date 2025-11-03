<?php
require_once 'config.php';

// Set header agar browser mendownload file sebagai Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="jadwal_kegiatan.xls"');

// Mulai output HTML table agar Excel langsung membacanya sebagai tabel
echo '<table border="1">';
echo '<tr>
        <th>No</th>
        <th>Nama Kegiatan</th>
        <th>Tanggal</th>
        <th>Waktu</th>
        <th>Deskripsi</th>
        <th>Status</th>
      </tr>';

$query = "SELECT * FROM jadwal ORDER BY tanggal, waktu";
$result = mysqli_query($conn, $query);

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_kegiatan']) . '</td>';
    echo '<td>' . $row['tanggal'] . '</td>';
    echo '<td>' . substr($row['waktu'], 0, 5) . '</td>';
    echo '<td>' . htmlspecialchars($row['deskripsi']) . '</td>';
    echo '<td>' . (isset($row['status']) && $row['status'] == 'selesai' ? 'Selesai' : 'Belum Selesai') . '</td>';
    echo '</tr>';
}
echo '</table>';
exit;
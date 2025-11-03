<?php
session_start();
require_once 'config.php';
require_once '../includes/functions.php';

// Debug session
// error_log('Ajax Session data: ' . print_r($_SESSION, true));

// Cek apakah user sudah login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login terlebih dahulu']);
    exit;
}

// Cek apakah user adalah admin untuk aksi yang membutuhkan hak admin
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Debug role
// error_log('Is Admin: ' . ($isAdmin ? 'true' : 'false'));
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'get':
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
            break;
        }
        $id = intval($_GET['id']);
        $query = "SELECT * FROM jadwal WHERE id = $id";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            echo json_encode($row);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        }
        break;

    case 'add':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menambah jadwal']);
            break;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
            break;
        }
        $nama_kegiatan = mysqli_real_escape_string($conn, $_POST['nama_kegiatan'] ?? '');
        $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal'] ?? '');
        $waktu = mysqli_real_escape_string($conn, $_POST['waktu'] ?? '');
        $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? '');

        if (!$nama_kegiatan || !$tanggal || !$waktu) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            break;
        }

        $query = "INSERT INTO jadwal (nama_kegiatan, tanggal, waktu, deskripsi) 
                  VALUES ('$nama_kegiatan', '$tanggal', '$waktu', '$deskripsi')";

        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Jadwal berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan jadwal: ' . mysqli_error($conn)]);
        }
        break;

    case 'update':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat mengubah jadwal']);
            break;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
            break;
        }
        $id = intval($_POST['id'] ?? 0);
        $nama_kegiatan = mysqli_real_escape_string($conn, $_POST['nama_kegiatan'] ?? '');
        $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal'] ?? '');
        $waktu = mysqli_real_escape_string($conn, $_POST['waktu'] ?? '');
        $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? '');

        if (!$id || !$nama_kegiatan || !$tanggal || !$waktu) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            break;
        }

        $query = "UPDATE jadwal SET 
                  nama_kegiatan = '$nama_kegiatan', 
                  tanggal = '$tanggal', 
                  waktu = '$waktu', 
                  deskripsi = '$deskripsi' 
                  WHERE id = $id";

        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Jadwal berhasil diperbarui']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jadwal: ' . mysqli_error($conn)]);
        }
        break;

    case 'delete':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menghapus jadwal']);
            break;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
            break;
        }
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
            break;
        }

        $query = "DELETE FROM jadwal WHERE id = $id";

        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus jadwal: ' . mysqli_error($conn)]);
        }
        break;

    case 'done':
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Hanya admin yang dapat menandai jadwal selesai']);
            break;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
            break;
        }
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
            break;
        }

        $query = "UPDATE jadwal SET status='selesai' WHERE id=$id";

        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Berhasil ditandai selesai']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update: ' . mysqli_error($conn)]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
        break;
}

mysqli_close($conn);

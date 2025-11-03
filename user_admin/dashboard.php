<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Set default values if session variables are not set
$username = $_SESSION['username'] ?? $_SESSION['email'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
$email = $_SESSION['email'] ?? '';

// Ambil total user dari database
$totalUsers = 0;
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalUsers = $row['total'];
}

// Ambil total kegiatan dari database
$totalKegiatan = 0;
$resultKegiatan = mysqli_query($conn, "SELECT COUNT(*) as total FROM jadwal");
if ($resultKegiatan) {
    $rowKegiatan = mysqli_fetch_assoc($resultKegiatan);
    $totalKegiatan = $rowKegiatan['total'];
}

// Ambil data absensi dari database lain (absensi_db)
$attendance = [];
$attendanceError = '';
$attendanceNote = '';
$absensi_db = 'absensi_db';
$possibleTables = ['attendance', 'absensi', 'records', 'attendance_log', 'log_absensi'];
$attendanceTable = null;
// Daftar tabel yang ada (akan diisi saat memeriksa INFORMATION_SCHEMA)
$existingTables = [];

// Cek apakah database absensi_db ada (bungkus dalam try/catch untuk mencegah uncaught exceptions)
try {
    $dbCheck = mysqli_query($conn, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . mysqli_real_escape_string($conn, $absensi_db) . "'");
    if ($dbCheck && mysqli_num_rows($dbCheck) > 0) {
        // Cari tabel yang mungkin berisi data absensi menggunakan INFORMATION_SCHEMA.TABLES
        $tblsQuery = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . mysqli_real_escape_string($conn, $absensi_db) . "'";
        $tblsRes = mysqli_query($conn, $tblsQuery);
        $existingTables = [];
        if ($tblsRes) {
            while ($trow = mysqli_fetch_assoc($tblsRes)) {
                $existingTables[] = $trow['TABLE_NAME'];
            }
        }

        // Jika admin memilih tabel tertentu melalui query string, gunakan itu jika valid
        if (isset($_GET['abs_table']) && !empty($existingTables)) {
            $candidate = mysqli_real_escape_string($conn, $_GET['abs_table']);
            if (in_array($candidate, $existingTables)) {
                $attendanceTable = $candidate;
                $attendanceNote = 'Menampilkan tabel terpilih: "' . $attendanceTable . '" dari database "' . $absensi_db . '".';
            }
        }

        foreach ($possibleTables as $tbl) {
            if (in_array($tbl, $existingTables)) {
                $attendanceTable = $tbl;
                break;
            }
        }

        // Jika tidak menemukan nama tabel yang sesuai, gunakan tabel pertama yang ada sebagai fallback
        if (!$attendanceTable && !empty($existingTables)) {
            $attendanceTable = $existingTables[0];
            $attendanceNote = 'Menggunakan tabel "' . $attendanceTable . '" dari database "' . $absensi_db . '" sebagai sumber absensi.';
        }

        if ($attendanceTable) {
            // Safely build fully-qualified table name
            $fqTable = '`' . str_replace('`', '``', $absensi_db) . '`.`' . str_replace('`', '``', $attendanceTable) . '`';

            // Tentukan kolom yang bisa digunakan untuk ORDER BY
            $orderCandidates = ['id', 'id_absensi', 'created_at', 'waktu_masuk', 'jam_masuk', 'check_in', 'time_in', 'waktu', 'timestamp', 'tanggal'];
            $orderColumn = null;
            // Ambil kolom tabel
            $colsRes = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . mysqli_real_escape_string($conn, $absensi_db) . "' AND TABLE_NAME='" . mysqli_real_escape_string($conn, $attendanceTable) . "'");
            $cols = [];
            if ($colsRes) {
                while ($crow = mysqli_fetch_assoc($colsRes)) {
                    $cols[] = $crow['COLUMN_NAME'];
                }
                foreach ($orderCandidates as $c) {
                    if (in_array($c, $cols)) {
                        $orderColumn = $c;
                        break;
                    }
                }
            }

            // Cek apakah tabel absensi punya kolom id_karyawan dan apakah ada tabel karyawan di absensi_db
            $selectExpr = '*';
            $joinClause = '';
            if (in_array('id_karyawan', $cols)) {
                // cari tabel karyawan/pegawai di existingTables
                $karyawanCandidates = ['karyawan','pegawai','employees','employee','staff'];
                $karyawanTable = null;
                foreach ($existingTables as $et) {
                    if (in_array(strtolower($et), $karyawanCandidates)) { $karyawanTable = $et; break; }
                }
                if ($karyawanTable) {
                    // ambil kolom tabel karyawan untuk menemukan kolom id dan nama
                    $kColsRes = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='" . mysqli_real_escape_string($conn, $absensi_db) . "' AND TABLE_NAME='" . mysqli_real_escape_string($conn, $karyawanTable) . "'");
                    $kCols = [];
                    if ($kColsRes) { while ($kr = mysqli_fetch_assoc($kColsRes)) { $kCols[] = $kr['COLUMN_NAME']; } }

                    $kIdCandidates = ['id_karyawan','id','karyawan_id','employee_id','id_kar'];
                    $kNameCandidates = ['nama','name','full_name','employee_name','nama_lengkap','nama_depan','first_name','firstname'];
                    $kIdCol = null; $kNameCol = null;
                    foreach ($kIdCandidates as $kc) { if (in_array($kc, $kCols)) { $kIdCol = $kc; break; } }
                    foreach ($kNameCandidates as $kn) { if (in_array($kn, $kCols)) { $kNameCol = $kn; break; } }

                    if ($kIdCol) {
                        // Bangun SELECT dengan LEFT JOIN jika memungkinkan
                        if ($kNameCol) {
                            $selectExpr = 'a.*, k.`' . str_replace('`','``',$kNameCol) . '` AS karyawan_name';
                        } else {
                            // jika tidak ada single name col, coba gabungkan first+last
                            $firstC = null; $lastC = null;
                            foreach (['first_name','firstname','nama_depan'] as $f) { if (in_array($f,$kCols)) { $firstC = $f; break; } }
                            foreach (['last_name','lastname','nama_belakang'] as $l) { if (in_array($l,$kCols)) { $lastC = $l; break; } }
                            if ($firstC && $lastC) {
                                $selectExpr = "a.*, CONCAT_WS(' ', k.`" . str_replace('`','``',$firstC) . "`, k.`" . str_replace('`','``',$lastC) . "`) AS karyawan_name";
                            } else {
                                // fallback: select a.* and no karyawan_name
                                $selectExpr = 'a.*';
                            }
                        }
                        $joinClause = ' LEFT JOIN `' . str_replace('`','``',$absensi_db) . '`.`' . str_replace('`','``',$karyawanTable) . '` k ON a.`id_karyawan` = k.`' . str_replace('`','``',$kIdCol) . '`';
                    }
                }
            }

            // Buat query akhir dengan alias a untuk tabel absensi
            if ($orderColumn) {
                $q = "SELECT " . $selectExpr . " FROM " . $fqTable . " AS a " . $joinClause . " ORDER BY a.`" . $orderColumn . "` DESC LIMIT 50";
            } else {
                // Jika tidak ada kolom urut yang dikenal, ambil 50 baris tanpa ORDER BY
                $q = "SELECT " . $selectExpr . " FROM " . $fqTable . " AS a " . $joinClause . " LIMIT 50";
            }
            // Simpan query untuk keperluan debug
            $attendanceQuery = $q;
            $r = @mysqli_query($conn, $q);
            if ($r) {
                while ($row = mysqli_fetch_assoc($r)) {
                    $attendance[] = $row;
                }
            } else {
                $attendanceError = 'Gagal mengambil data absensi: ' . mysqli_error($conn);
            }
        } else {
            // Tampilkan daftar tabel yang ada di absensi_db agar admin tahu
            $tbls = [];
            $rt = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='" . mysqli_real_escape_string($conn, $absensi_db) . "'");
            if ($rt) {
                while ($rr = mysqli_fetch_assoc($rt)) {
                    $tbls[] = $rr['TABLE_NAME'];
                }
            }
            $attendanceError = 'Tidak menemukan tabel absensi di ' . $absensi_db . '. Tabel yang ada: ' . implode(', ', $tbls);
        }
    } else {
        $attendanceError = 'Database "' . $absensi_db . '" tidak ditemukan pada server.';
    }
} catch (\Throwable $e) {
    // Tangkap error mysqli_sql_exception atau error lain dan tampilkan pesan ramah
    $attendanceError = 'Terjadi kesalahan saat mengakses database absensi: ' . $e->getMessage();
}

// Siapkan kolom yang akan ditampilkan secara preferensial jika data ditemukan
$displayColumns = [];
$columnLabels = [];
if (!empty($attendance)) {
    $firstRow = $attendance[0];
    $allCols = array_keys($firstRow);

    // Prefered column order and friendly labels
    $preferred = [
        'user_id' => 'User ID',
        'user' => 'User',
        'karyawan_name' => 'Nama Karyawan',
        'user_email' => 'Email',
        'email' => 'Email',
        'nama' => 'Nama',
        'name' => 'Nama',
        'tanggal' => 'Tanggal',
        'waktu_masuk' => 'Waktu Masuk',
        'jam_masuk' => 'Waktu Masuk',
        'check_in' => 'Waktu Masuk',
        'time_in' => 'Waktu Masuk',
        'waktu_keluar' => 'Waktu Keluar',
        'jam_keluar' => 'Waktu Keluar',
        'check_out' => 'Waktu Keluar',
        'time_out' => 'Waktu Keluar',
        'status' => 'Status',
        'keterangan' => 'Keterangan',
        'note' => 'Keterangan',
        'created_at' => 'Waktu'
    ];

    // Add preferred columns that exist
    foreach ($preferred as $col => $label) {
        if (in_array($col, $allCols) && !in_array($col, $displayColumns)) {
            $displayColumns[] = $col;
            $columnLabels[$col] = $label;
        }
    }

    // Append remaining columns
    foreach ($allCols as $col) {
        if (!in_array($col, $displayColumns)) {
            $displayColumns[] = $col;
            // humanize label
            $columnLabels[$col] = ucwords(str_replace(['_','-'], [' ', ' '], $col));
        }
    }

    // DETEKSI KOLOM NAMA/IDENTITAS UNTUK DITAMPILKAN (tambah virtual column 'display_name' di depan jika perlu)
    $nameCandidates = ['name','nama','full_name','nama_lengkap','employee_name','karyawan','user_name','username','display_name'];
    $firstNameCols = ['first_name','firstname','given_name'];
    $lastNameCols = ['last_name','lastname','family_name'];
    $emailCandidates = ['user_email','email','email_address'];

    $nameSource = null;
    foreach ($nameCandidates as $nc) {
        if (in_array($nc, $allCols)) { $nameSource = $nc; break; }
    }
    // Jika tidak ada single name column, cek apakah ada first+last
    if (!$nameSource) {
        $foundFirst = null; $foundLast = null;
        foreach ($firstNameCols as $f) { if (in_array($f, $allCols)) { $foundFirst = $f; break; } }
        foreach ($lastNameCols as $l) { if (in_array($l, $allCols)) { $foundLast = $l; break; } }
        if ($foundFirst && $foundLast) {
            $nameSource = [$foundFirst, $foundLast];
        }
    }
    // Jika masih tidak ada, fallback ke email
    if (!$nameSource) {
        foreach ($emailCandidates as $ec) { if (in_array($ec, $allCols)) { $nameSource = $ec; break; } }
    }

    // Jika ditemukan sumber nama/fallback, tambahkan virtual kolom display_name di depan
    if ($nameSource) {
        array_unshift($displayColumns, 'display_name');
        $columnLabels['display_name'] = 'Nama';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Modern Auth System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #f97316;
            --dark: #1e293b;
            --light: #f8fafc;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f1f5f9;
            color: var(--dark);
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 250px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .sidebar-header h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 13px;
            color: #64748b;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #64748b;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
        }
        
        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
        }
        
        .sidebar-menu a:hover::before, 
        .sidebar-menu a.active::before {
            content: '';
            position: absolute;
            left: 0;
            width: 3px;
            height: 30px;
            background: var(--primary);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
        }
        
        .header h2 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .user-profile .dropdown {
            position: relative;
        }
        
        .user-profile .dropdown-toggle {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-profile .dropdown-menu {
            position: absolute;
            right: 0;
            top: 50px;
            background: white;
            width: 200px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 10px 0;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .user-profile .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            top: 45px;
        }
        
        .user-profile .dropdown-menu a {
            display: block;
            padding: 8px 15px;
            color: #64748b;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .user-profile .dropdown-menu a:hover {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
        }
        
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .card-icon.blue {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
        }
        
        .card-icon.green {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .card-icon.orange {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .card-icon.red {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }
        
        .card h3 {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 5px;
        }
        
        .card h2 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .card-footer {
            display: flex;
            align-items: center;
            margin-top: 10px;
            font-size: 13px;
        }
        
        .card-footer i {
            margin-right: 5px;
        }
        
        .card-footer.positive {
            color: var(--success);
        }
        
        .card-footer.negative {
            color: var(--error);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        @media (min-width: 768px) {
            .content-grid {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-header img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 3px solid rgba(99, 102, 241, 0.2);
            object-fit: cover;
        }
        
        .profile-header h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .profile-header p {
            color: #64748b;
            font-size: 14px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 3px 10px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .profile-details {
            margin-top: 20px;
        }
        
        .profile-detail {
            display: flex;
            margin-bottom: 10px;
        }
        
        .profile-detail i {
            width: 20px;
            margin-right: 10px;
            color: var(--primary);
        }
        
        .admin-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        .admin-panel h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .admin-feature {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .admin-feature i {
            width: 30px;
            height: 30px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        
        .admin-feature:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
        <div style="display: flex; align-items: center;">
            <img src="<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'https://placehold.co/100x100'); ?>" alt="User Avatar">
                <div>
                    <h3><?php echo htmlspecialchars($username); ?></h3>
                    <p><?php echo htmlspecialchars($role); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Menu -->
        <div class="sidebar-menu">
            <a href="#" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="account.php">
                <i class="fas fa-user-circle"></i>
                <span>Informasi Akun</span>
            </a>
            <?php if (isAdmin()): ?>
                <a href="admin_panel.php">
                    <i class="fas fa-users-cog"></i>
                    <span>Admin Panel</span>
                </a>
                <a href="jadual_kegiatan.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Jadual</span>
                </a>
                <a href="peminjaman.php">
                    <i class="fas fa-box-open"></i>
                    <span>Peminjaman Barang</span>
                </a>
            <?php endif; ?>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h2>Dashboard</h2>
            
            <div class="user-profile">
                <img src="<?php echo htmlspecialchars($_SESSION['avatar'] ?? 'https://placehold.co/100x100'); ?>" alt="User Profile Picture">
                <div class="dropdown">
                    <div class="dropdown-toggle" onclick="toggleDropdown()">
                        <span><?php echo htmlspecialchars($username); ?></span>
                        <i class="fas fa-chevron-down" style="margin-left: 5px;"></i>
                    </div>
                    <!-- Dropdown Menu -->
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="jadual_kegiatan.php"><i class="fas fa-chart-line"></i><sp>jadual</sp></a>
                        <?php if (isAdmin()): ?>
                            <a href="peminjaman.php"><i class="fas fa-box-open"></i> Peminjaman Barang</a>
                        <?php endif; ?>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Cards -->
        <div class="cards">
            <div class="card">
                <div class="card-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Total Users</h3>
                <h2><?php echo number_format($totalUsers); ?></h2>
                <div class="card-footer positive">
                    <i class="fas fa-arrow-up"></i> 12% from last month
                </div>
            </div>
            
            <div class="card">
                <div class="card-icon orange">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3>Kegiatan</h3>
                <h2><?php echo number_format($totalKegiatan); ?></h2>
                <div class="card-footer positive">
                    <i class="fas fa-arrow-up"></i> Data real-time
                </div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Main Content -->
            <div class="card">
                <h3>Menu Utama</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                    <a href="account.php" style="text-decoration: none;">
                        <div class="admin-feature" style="background: rgba(99, 102, 241, 0.1); padding: 20px; border-radius: 8px;">
                            <i class="fas fa-user-circle" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                            <div>
                                <h4 style="color: var(--dark);">Informasi Akun</h4>
                                <p style="color: #64748b;">Lihat dan edit profil Anda</p>
                            </div>
                        </div>
                    </a>
                    
                    <?php if (isAdmin()): ?>
                    <a href="inventory.php" style="text-decoration: none;">
                        <div class="admin-feature" style="background: rgba(99, 102, 241, 0.1); padding: 20px; border-radius: 8px;">
                            <i class="fas fa-box" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                            <div>
                                <h4 style="color: var(--dark);">Inventory</h4>
                                <p style="color: #64748b;">Kelola barang dan peminjaman</p>
                            </div>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <a href="jadual_kegiatan.php" style="text-decoration: none;">
                        <div class="admin-feature" style="background: rgba(99, 102, 241, 0.1); padding: 20px; border-radius: 8px;">
                            <i class="fas fa-calendar-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                            <div>
                                <h4 style="color: var(--dark);">Jadual Kegiatan</h4>
                                <p style="color: #64748b;">Lihat dan kelola jadual</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Side Content -->
            <div>
                <!-- Absensi section -->
                <div class="card" style="margin-bottom:20px;">
                    <h3>Absensi Terakhir</h3>
                    <?php if ($attendanceError): ?>
                        <p style="color:#ef4444"><?php echo htmlspecialchars($attendanceError); ?></p>
                    <?php /* attendanceNote removed: admin debug now provides more detailed diagnostics */ ?>
                    <?php endif; ?>

                    <?php if (isAdmin() && !empty($existingTables)): ?>
                        <form method="GET" style="margin:10px 0;">
                            <label for="abs_table">Pilih sumber absensi:</label>
                            <select id="abs_table" name="abs_table" onchange="this.form.submit()" style="margin-left:8px;padding:6px;border-radius:4px;">
                                <option value="">-- pilih --</option>
                                <?php foreach ($existingTables as $et): ?>
                                    <option value="<?php echo htmlspecialchars($et); ?>" <?php echo (isset($_GET['abs_table']) && $_GET['abs_table']==$et) ? 'selected' : ''; ?>><?php echo htmlspecialchars($et); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>



                    <?php if (empty($attendance)): ?>
                        <p>Tidak ada data absensi tersedia.</p>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <?php foreach ($displayColumns as $col): ?>
                                            <th style="padding:8px;border-bottom:1px solid #e2e8f0; text-align:left;"><?php echo htmlspecialchars($columnLabels[$col] ?? $col); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $row): ?>
                                        <?php
                                        // compute virtual display_name if requested
                                        if (in_array('display_name', $displayColumns)) {
                                            if (is_array($nameSource)) {
                                                $fn = $row[$nameSource[0]] ?? '';
                                                $ln = $row[$nameSource[1]] ?? '';
                                                $row['display_name'] = trim($fn . ' ' . $ln);
                                            } else {
                                                $ns = $nameSource;
                                                $row['display_name'] = $row[$ns] ?? '';
                                            }
                                            if (empty($row['display_name'])) {
                                                // fallback to email or first available column
                                                foreach (['email','user_email'] as $f) { if (isset($row[$f]) && $row[$f]) { $row['display_name'] = $row[$f]; break; } }
                                                if (empty($row['display_name'])) { $row['display_name'] = array_values($row)[0] ?? ''; }
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <?php foreach ($displayColumns as $col): ?>
                                                <td style="padding:8px;border-bottom:1px solid #e2e8f0;"><?php echo htmlspecialchars($row[$col] ?? ''); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (isAdmin()): ?>
                    <div class="admin-panel">
                        <h3>Admin Tools</h3>

                        <div class="admin-feature">
                            <i class="fas fa-box"></i>
                            <div>
                                <h4>Inventory</h4>
                                <p><a href="inventory.php" style="color:var(--primary);text-decoration:none;">Manage inventory &amp; borrowings</a></p>
                            </div>
                        </div>

                        <div class="admin-feature">
                            <i class="fas fa-users"></i>
                            <div>
                                <h4>Manage Users</h4>
                                <p>Add, edit or remove system users</p>
                            </div>
                        </div>

                        <div class="admin-feature">
                            <i class="fas fa-shield-alt"></i>
                            <div>
                                <h4>Permissions</h4>
                                <p>Configure user roles and permissions</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="profile-card" style="margin-top: 20px;">
                    <h3>Quick Actions</h3>
                    
                    <div class="admin-feature">
                        <i class="fas fa-user-plus"></i>
                        <div>
                            <h4>Add New User</h4>
                            <p>Create a new system account</p>
                        </div>
                    </div>
                    
                    <div class="admin-feature">
                        <i class="fas fa-file-alt"></i>
                        <div>
                            <h4>Generate Report</h4>
                            <p>Create a system activity report</p>
                        </div>
                    </div>
                    
                    <div class="admin-feature">
                        <i class="fas fa-bell"></i>
                        <div>
                            <h4>Notifications</h4>
                            <p>View and manage notifications</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleDropdown() {
            document.getElementById('dropdownMenu').classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-toggle')) {
                var dropdowns = document.getElementsByClassName("dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>

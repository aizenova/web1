CREATE DATABASE absensi_db;

CREATE DATABASE IF NOT EXISTS absensi_db;
USE absensi_db;

-- Table karyawan: employees with card UID
CREATE TABLE IF NOT EXISTS karyawan (
    id_karyawan INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    id_card VARCHAR(50) NOT NULL UNIQUE,
    uid_kartu VARCHAR(255) UNIQUE,
    -- Jam kerja default per karyawan (nullable). Format TIME HH:MM:SS
    jam_mulai TIME DEFAULT NULL,
    jam_akhir TIME DEFAULT NULL
);

-- Table absensi: attendance records
CREATE TABLE IF NOT EXISTS absensi (
    id_absensi INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME DEFAULT NULL,
    jam_keluar TIME DEFAULT NULL,
    CONSTRAINT fk_absensi_karyawan FOREIGN KEY (id_karyawan)
        REFERENCES karyawan(id_karyawan)
        ON DELETE CASCADE
        ON UPDATE CASCADE
    ,
    UNIQUE KEY unique_absen (id_karyawan, tanggal)
);

-- Table admin: system administrators
CREATE TABLE IF NOT EXISTS admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Table izin: records of izin keluar or permintaan pulang with reason and status
CREATE TABLE IF NOT EXISTS izin (
    id_izin INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    -- tambah 'terlambat' untuk permintaan/rekaman terlambat
    jenis ENUM('izin_keluar','permintaan_pulang','langsung_pulang','terlambat') NOT NULL,
    alasan TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected','failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_izin_karyawan FOREIGN KEY (id_karyawan)
        REFERENCES karyawan(id_karyawan)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
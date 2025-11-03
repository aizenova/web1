CREATE DATABASE IF NOT EXISTS Peminjaman_barang_db;
USE Peminjaman_barang_db;

-- Table karyawan: employees with card/RFID information
CREATE TABLE IF NOT EXISTS karyawan (
    id_karyawan INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    id_card VARCHAR(50) NOT NULL UNIQUE,
    uid_kartu VARCHAR(255) UNIQUE,
    -- allow storing division/department and position optionally
    divisi VARCHAR(100),
    jabatan VARCHAR(100),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table kategori_barang: categories for items
CREATE TABLE IF NOT EXISTS kategori_barang (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT
);

-- Table barang: list of available items (primary table)
CREATE TABLE IF NOT EXISTS barang (
    id_barang INT AUTO_INCREMENT PRIMARY KEY,
    id_kategori INT,
    kode_barang VARCHAR(50) NOT NULL UNIQUE,
    nama_barang VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    jumlah_total INT NOT NULL DEFAULT 1,
    jumlah_tersedia INT NOT NULL DEFAULT 1,
    lokasi_penyimpanan VARCHAR(100),
    kondisi ENUM('baik', 'rusak_ringan', 'rusak_berat') NOT NULL DEFAULT 'baik',
    status ENUM('tersedia','dipinjam','dalam_pemeliharaan') NOT NULL DEFAULT 'tersedia',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori_barang(id_kategori)
);

-- Create an `item` table as an alias/duplicate of `barang` so code that expects
-- a table named `item` will work. If you already have an `item` table, this
-- statement will do nothing due to IF NOT EXISTS — keep schemas synchronized manually.
CREATE TABLE IF NOT EXISTS item LIKE barang;

-- Table peminjaman: records of borrowed items
CREATE TABLE IF NOT EXISTS peminjaman (
    id_peminjaman INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    -- allow NULL here because we use ON DELETE SET NULL on the foreign key
    id_barang INT DEFAULT NULL,
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    tanggal_pinjam DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tanggal_kembali_rencana DATETIME,
    tanggal_kembali_aktual DATETIME,
    tujuan_peminjaman TEXT,
    lokasi_penggunaan VARCHAR(100),
    status ENUM('pending','approved','rejected','dipinjam','dikembalikan','terlambat') NOT NULL DEFAULT 'pending',
    catatan TEXT,
    approved_by INT,
    approved_at DATETIME,
    CONSTRAINT fk_peminjaman_barang FOREIGN KEY (id_barang)
        REFERENCES barang(id_barang)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- Table riwayat_peminjaman: history of all borrowing transactions
CREATE TABLE IF NOT EXISTS riwayat_peminjaman (
    id_riwayat INT AUTO_INCREMENT PRIMARY KEY,
    id_peminjaman INT NOT NULL,
    id_karyawan INT NOT NULL,
    id_barang INT NOT NULL,
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    action ENUM('created','approved','rejected','borrowed','returned','overdue') NOT NULL,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    keterangan TEXT,
    FOREIGN KEY (id_peminjaman) REFERENCES peminjaman(id_peminjaman),
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang)
);

-- Helper view: active_borrowings - shows current borrowed items with employee info
CREATE OR REPLACE VIEW active_borrowings AS
SELECT 
    p.*,
    k.nama AS nama_karyawan,
    k.id_card,
    k.uid_kartu,
    k.divisi,
    k.jabatan,
    b.kode_barang,
    b.nama_barang,
    b.lokasi_penyimpanan,
    b.kondisi
FROM peminjaman p
JOIN karyawan k ON p.id_karyawan = k.id_karyawan
JOIN barang b ON p.id_barang = b.id_barang
WHERE p.status = 'dipinjam'
    AND p.tanggal_kembali_aktual IS NULL;

-- Helper view: employee_borrow_history - shows complete borrowing history per employee
CREATE OR REPLACE VIEW employee_borrow_history AS
SELECT 
    k.id_karyawan,
    k.nama AS nama_karyawan,
    k.id_card,
    k.divisi,
    k.jabatan,
    COUNT(p.id_peminjaman) AS total_peminjaman,
    COUNT(CASE WHEN p.status = 'dipinjam' THEN 1 END) AS peminjaman_aktif,
    COUNT(CASE WHEN p.status = 'terlambat' THEN 1 END) AS peminjaman_terlambat,
    MAX(p.tanggal_pinjam) AS peminjaman_terakhir
FROM karyawan k
LEFT JOIN peminjaman p ON k.id_karyawan = p.id_karyawan
GROUP BY k.id_karyawan, k.nama, k.id_card, k.divisi, k.jabatan;
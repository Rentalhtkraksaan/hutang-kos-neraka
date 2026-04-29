-- ============================================================
--  DATABASE: utang_hoirul
--  Digenerate: 28 April 2026
--  Aplikasi: Tagihan Akulaku Hoirul
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ------------------------------------------------------------
-- Buat Database
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `utang_hoirul`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `utang_hoirul`;

-- ============================================================
-- Tabel: settings
-- Menyimpan konfigurasi: saldo, fee, dan kredensial admin
-- ============================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('saldo',           '2065000'),   -- Sisa tagihan awal (Rp)
('fee_admin',       '0'),          -- Akumulasi fee 5% admin
('fee_nama_baik',   '0'),          -- Akumulasi fee 12% nama baik
('fee_akomodasi',   '0'),          -- Akumulasi fee 3% akomodasi
('admin_username',  'admin'),      -- Username login admin
('admin_password',  'admin123');   -- Password login admin

-- ============================================================
-- Tabel: transactions
-- Menyimpan riwayat semua transaksi (utang & pembayaran)
-- ============================================================
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id`            INT(11)        NOT NULL AUTO_INCREMENT,
  `date`          DATETIME       NOT NULL,
  `type`          VARCHAR(255)   COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount`        DECIMAL(15,2)  NOT NULL COMMENT 'Positif = tambah utang, Negatif = pembayaran',
  `current_saldo` DECIMAL(15,2)  NOT NULL COMMENT 'Saldo setelah transaksi ini',
  `split_details` TEXT           COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON breakdown fee (untuk pembayaran)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal (saldo pembuka)
INSERT INTO `transactions` (`date`, `type`, `amount`, `current_saldo`, `split_details`) VALUES
(NOW(), 'Inisiasi Saldo Awal', 2065000.00, 2065000.00, NULL);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- CATATAN PENTING:
--
--  Cara Import:
--  1. Buka phpMyAdmin di http://localhost/phpmyadmin
--  2. Klik tab "Import"
--  3. Pilih file ini (database.sql) → klik "Go"
--
--  Atau via terminal:
--  mysql -u root -p < database.sql
--
--  Login Admin Aplikasi:
--  URL      : http://localhost/utang/admin
--  Username : admin
--  Password : admin123
--
--  Struktur Fee Potongan Pembayaran (total 20%):
--  - Admin         : 5%
--  - Nama Baik     : 12%
--  - Akomodasi     : 3%
-- ============================================================

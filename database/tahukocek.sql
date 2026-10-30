CREATE DATABASE IF NOT EXISTS tahukocek
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tahukocek;

-- ──────────────────────────────────────────────────────────
--  TABEL: users
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(150)        NOT NULL,
  email       VARCHAR(191)        NOT NULL UNIQUE,
  phone       VARCHAR(20)         NOT NULL DEFAULT '',
  password    VARCHAR(255)        NOT NULL,
  role        ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
--  TABEL: toko (profil & pengaturan toko)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS toko (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_toko           VARCHAR(150)  NOT NULL DEFAULT 'Tahu Kocek Pusat',
  nama_pemilik        VARCHAR(150)  NOT NULL DEFAULT 'Dina Putri',
  slogan              VARCHAR(255)  DEFAULT 'Pedasnya bikin nagih',
  deskripsi           TEXT,
  whatsapp            VARCHAR(20)   DEFAULT '0812-3456-7800',
  email               VARCHAR(191)  DEFAULT 'halo@tahukocek.id',
  alamat              VARCHAR(255)  DEFAULT '',
  kota                VARCHAR(100)  DEFAULT 'Kota Malang',
  provinsi            VARCHAR(100)  DEFAULT 'Jawa Timur',
  kode_pos            VARCHAR(10)   DEFAULT '65141',
  logo_url            VARCHAR(255)  DEFAULT '',
  terima_pesanan      TINYINT(1)    NOT NULL DEFAULT 1,
  ambil_di_toko       TINYINT(1)    NOT NULL DEFAULT 1,
  antar_lokal         TINYINT(1)    NOT NULL DEFAULT 1,
  estimasi_menit_min  SMALLINT      NOT NULL DEFAULT 15,
  estimasi_menit_max  SMALLINT      NOT NULL DEFAULT 20,
  minimum_pesanan     INT UNSIGNED  NOT NULL DEFAULT 12000,
  metode_qris         TINYINT(1)    NOT NULL DEFAULT 1,
  qris_url            VARCHAR(255)  NOT NULL DEFAULT '',
  metode_tunai        TINYINT(1)    NOT NULL DEFAULT 1,
  metode_transfer     TINYINT(1)    NOT NULL DEFAULT 1,
  notif_whatsapp      TINYINT(1)    NOT NULL DEFAULT 1,
  notif_email         TINYINT(1)    NOT NULL DEFAULT 0,
  notif_bunyi         TINYINT(1)    NOT NULL DEFAULT 1,
  updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
--  TABEL: jam_operasional
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jam_operasional (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hari     ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  aktif    TINYINT(1)   NOT NULL DEFAULT 1,
  buka     TIME         NOT NULL DEFAULT '10:00:00',
  tutup    TIME         NOT NULL DEFAULT '21:00:00'
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
--  TABEL: varian
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS varian (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode        VARCHAR(20)   NOT NULL UNIQUE,
  nama        VARCHAR(150)  NOT NULL,
  deskripsi   TEXT,
  harga       INT UNSIGNED  NOT NULL DEFAULT 0,
  stok        SMALLINT      NOT NULL DEFAULT 0,
  emoji       VARCHAR(10)   NOT NULL DEFAULT '🧆',
  sku         VARCHAR(50)   NOT NULL DEFAULT '1 porsi / 10 pcs',
  foto_url    VARCHAR(255)  DEFAULT '',
  status      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
--  TABEL: pesanan
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pesanan (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nomor           VARCHAR(20)   NOT NULL UNIQUE,
  user_id         INT UNSIGNED  DEFAULT NULL,
  nama_pelanggan  VARCHAR(150)  NOT NULL,
  phone_pelanggan VARCHAR(20)   NOT NULL DEFAULT '',
  metode_ambil    ENUM('ambil','antar') NOT NULL DEFAULT 'ambil',
  metode_bayar    ENUM('QRIS','Tunai','Transfer bank') NOT NULL DEFAULT 'QRIS',
  status_bayar    ENUM('Lunas','Belum bayar','Dibatalkan') NOT NULL DEFAULT 'Belum bayar',
  status          ENUM('baru','diproses','siap','selesai','batal') NOT NULL DEFAULT 'baru',
  subtotal        INT UNSIGNED  NOT NULL DEFAULT 0,
  biaya_tambahan  INT UNSIGNED  NOT NULL DEFAULT 0,
  total           INT UNSIGNED  NOT NULL DEFAULT 0,
  catatan         TEXT,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
--  TABEL: item_pesanan
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS item_pesanan (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id  INT UNSIGNED  NOT NULL,
  varian_id   INT UNSIGNED  NOT NULL,
  nama_varian VARCHAR(150)  NOT NULL,
  harga_satuan INT UNSIGNED NOT NULL,
  qty         SMALLINT      NOT NULL DEFAULT 1,
  subtotal    INT UNSIGNED  NOT NULL DEFAULT 0,
  FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
  FOREIGN KEY (varian_id)  REFERENCES varian(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────────────────
--  SEED: users
--  Admin  : admin@tahukocek.com / tahu123
--  User   : user@tahukocek.com  / user123
-- ──────────────────────────────────────────────────────────
INSERT INTO users (nama, email, phone, password, role) VALUES
  ('Dina Putri',  'admin@tahukocek.com', '081234567890',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
  ('Pelanggan',   'user@tahukocek.com',  '082233445566',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');
-- Note: hash di atas = password('password') dari bcrypt cost 12.
-- Ganti dengan hash yang benar menggunakan password_hash() di PHP.

-- ──────────────────────────────────────────────────────────
--  SEED: toko
-- ──────────────────────────────────────────────────────────
INSERT INTO toko (
  nama_toko, nama_pemilik, slogan, deskripsi,
  whatsapp, email, alamat, kota, provinsi, kode_pos
) VALUES (
  'Tahu Kocek Pusat', 'Dina Putri',
  'Pedasnya bikin nagih',
  'Tahu crispy dengan sambal bawang khas Malang. Gurih, renyah, dan pedasnya past! Dibuat segar untuk setiap pesanan.',
  '0812-3456-7800', 'halo@tahukocek.id',
  'Jl. Soekarno Hatta No. 28, Kel. Jatimulyo, Kec. Lowokwaru',
  'Kota Malang', 'Jawa Timur', '65141'
);

-- ──────────────────────────────────────────────────────────
--  SEED: jam_operasional (Senin–Jumat aktif, Sabtu–Minggu nonaktif)
-- ──────────────────────────────────────────────────────────
INSERT INTO jam_operasional (hari, aktif, buka, tutup) VALUES
  ('Senin',  1, '10:00:00', '21:00:00'),
  ('Selasa', 1, '10:00:00', '21:00:00'),
  ('Rabu',   1, '10:00:00', '21:00:00'),
  ('Kamis',  1, '10:00:00', '21:00:00'),
  ('Jumat',  1, '10:00:00', '21:00:00'),
  ('Sabtu',  0, '10:00:00', '21:00:00'),
  ('Minggu', 0, '10:00:00', '21:00:00');

-- ──────────────────────────────────────────────────────────
--  SEED: varian
-- ──────────────────────────────────────────────────────────
INSERT INTO varian (kode, nama, deskripsi, harga, stok, emoji, status) VALUES
  ('TK-ORI', 'Tahu Kocek Original',     'Tahu crispy dengan sambal bawang khas. Gurih, renyah, dan pedasnya past!', 12000, 48, '🧆', 'aktif'),
  ('TK-P33', 'Tahu Kocek Pedas Level 3','Level pedas tinggi untuk yang suka tantangan.',                           14000,  8, '🌶️','aktif'),
  ('TK-KJU', 'Tahu Kocek Keju',         'Taburan keju lumer di atas tahu crispy.',                                15000, 32, '🧀', 'aktif'),
  ('TK-BLD', 'Tahu Kocek Balado',       'Bumbu balado merah yang kaya rempah.',                                   13000, 25, '🍛', 'aktif'),
  ('TK-DJ',  'Tahu Kocek Daun Jeruk',   'Aroma daun jeruk yang segar berpadu sambal.',                            14000, 40, '🌿', 'aktif'),
  ('TK-BBQ', 'Tahu Kocek BBQ',          'Varian BBQ smoky untuk selera baru.',                                    14000,  0, '🔥', 'nonaktif');

-- ──────────────────────────────────────────────────────────
--  SEED: pesanan + item_pesanan (demo data)
-- ──────────────────────────────────────────────────────────
INSERT INTO pesanan
  (nomor, nama_pelanggan, phone_pelanggan, metode_ambil, metode_bayar, status_bayar, status, subtotal, total, catatan)
VALUES
  ('TK-1064','Rani Maharani', '0812-3456-7890','ambil','QRIS',         'Lunas',      'baru',     38000,38000,'Sambal Original dipisah ya, terima kasih.'),
  ('TK-1063','Bima Pratama',  '0813-9876-5432','ambil','Tunai',        'Belum bayar','baru',     29000,29000,''),
  ('TK-1062','Siti Aisyah',   '0856-1122-3344','antar','QRIS',         'Lunas',      'diproses', 28000,28000,''),
  ('TK-1061','Andi Saputra',  '0877-5566-7788','ambil','Transfer bank','Lunas',      'diproses', 25000,25000,''),
  ('TK-1060','Nadia Putri',   '0895-4433-2211','ambil','QRIS',         'Lunas',      'siap',     30000,30000,'Minta kantong besar ya!'),
  ('TK-1059','Fajar Ramadhan','0812-0011-2233','ambil','Tunai',        'Lunas',      'selesai',  40000,40000,''),
  ('TK-1058','Dewi Lestari',  '0856-9988-7766','ambil','QRIS',         'Lunas',      'selesai',  26000,26000,''),
  ('TK-1057','Rizky Maulana', '0821-3344-5566','ambil','Tunai',        'Dibatalkan', 'batal',    14000,14000,'');

INSERT INTO item_pesanan (pesanan_id, varian_id, nama_varian, harga_satuan, qty, subtotal) VALUES
  (1, 1,'Tahu Kocek Original',    12000, 2, 24000),
  (1, 2,'Tahu Kocek Pedas Level 3',14000,1, 14000),
  (2, 3,'Tahu Kocek Keju',        15000, 1, 15000),
  (2, 5,'Tahu Kocek Daun Jeruk',  14000, 1, 14000),
  (3, 2,'Tahu Kocek Pedas Level 3',14000,2, 28000),
  (4, 1,'Tahu Kocek Original',    12000, 1, 12000),
  (4, 4,'Tahu Kocek Balado',      13000, 1, 13000),
  (5, 3,'Tahu Kocek Keju',        15000, 2, 30000),
  (6, 1,'Tahu Kocek Original',    12000, 1, 12000),
  (6, 5,'Tahu Kocek Daun Jeruk',  14000, 2, 28000),
  (7, 4,'Tahu Kocek Balado',      13000, 2, 26000),
  (8, 2,'Tahu Kocek Pedas Level 3',14000,1, 14000);

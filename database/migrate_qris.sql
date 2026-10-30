USE tahukocek;

ALTER TABLE toko
  ADD COLUMN qris_url VARCHAR(255) NOT NULL DEFAULT '' AFTER metode_qris;

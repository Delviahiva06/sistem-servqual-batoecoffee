-- Aman dijalankan berkali-kali (tidak error jika kolom sudah ada)
ALTER TABLE tbl_responden
ADD COLUMN IF NOT EXISTS jenis_kelamin VARCHAR(20) NULL AFTER nama;

-- Isi data lama yang kosong/null agar konsisten
UPDATE tbl_responden
SET jenis_kelamin = 'Laki-laki'
WHERE jenis_kelamin IS NULL OR TRIM(jenis_kelamin) = '';

-- Standarisasi tipe kolom
ALTER TABLE tbl_responden
MODIFY COLUMN jenis_kelamin ENUM('Laki-laki','Perempuan') NOT NULL;

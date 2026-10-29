-- Run once against an existing lab_db before deploying the updated admin.php.
USE lab_db;

ALTER TABLE users
    ADD COLUMN Role ENUM('user', 'admin') NOT NULL DEFAULT 'user';

-- Bootstrap the first administrator using an existing, verified account.
UPDATE users
SET Role = 'admin'
WHERE Email = 'tuq78148@temple.edu' AND EmailVerified = 1;

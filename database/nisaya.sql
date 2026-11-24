CREATE DATABASE IF NOT EXISTS nisaya_db
-- Membuat database bernama nisaya_db jika belum ada

CHARACTER SET utf8mb4
-- Menggunakan pengodean karakter yang mendukung Unicode

COLLATE utf8mb4_unicode_ci;
-- Mengatur cara membandingkan dan mengurutkan teks
-- berdasarkan aturan Unicode, tanpa membedakan huruf besar dan kecil

USE nisaya_db;


-- ==========================================
-- 1. USERS
-- Menyimpan data akun pengguna
-- ==========================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    profile_picture VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL
);


-- ==========================================
-- 2. CYCLE PROFILES
-- Menyimpan profil dan kebiasaan siklus pengguna
-- ==========================================

CREATE TABLE cycle_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    last_period_start DATE NULL,
    average_period_days TINYINT NULL,
    average_purity_days SMALLINT NULL,
    cycle_regular VARCHAR(20) NOT NULL DEFAULT 'unknown',
    profile_completed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


-- ==========================================
-- 3. MENSTRUAL RECORDS
-- Menyimpan catatan periode menstruasi
-- ==========================================

CREATE TABLE menstrual_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ongoing',
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    INDEX (user_id),

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


-- ==========================================
-- 4. BLEEDING EVENTS
-- Menyimpan detail kejadian perdarahan
-- ==========================================

CREATE TABLE bleeding_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    observation VARCHAR(20) NOT NULL,
    notes TEXT NULL,

    INDEX (record_id),

    FOREIGN KEY (record_id)
        REFERENCES menstrual_records(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


-- ==========================================
-- 5. CALENDAR PREDICTIONS
-- Menyimpan hasil prediksi kalender
-- ==========================================

CREATE TABLE calendar_predictions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    predicted_start DATE NOT NULL,
    predicted_end DATE NOT NULL,
    prediction_status VARCHAR(20) NOT NULL DEFAULT 'upcoming',
    calculated_at DATETIME NOT NULL,

    INDEX (user_id),

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


-- ==========================================
-- 6. NOTIFICATIONS
-- Menyimpan notifikasi pengguna
-- ==========================================
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    record_id INT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    link VARCHAR(255) NULL,
    article_id INT UNSIGNED NULL,
    prediction_source VARCHAR(20) NULL,
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ==========================================
-- 7. EDUCATION ARTICLES
-- Menyimpan materi dan artikel edukasi
-- ==========================================
CREATE TABLE education_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    category VARCHAR(100) NOT NULL,
    icon VARCHAR(50) NULL DEFAULT 'tulip',
    image VARCHAR(255) NULL,
    description TEXT NOT NULL,
    content LONGTEXT NOT NULL,
    references_text TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    author_id INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,

    FOREIGN KEY (author_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- ==========================================
-- 8. PUSH SUBSCRIPTIONS
-- Menyimpan data langganan Web Push
-- ==========================================

CREATE TABLE push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    public_key TEXT NOT NULL,
    auth_token TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX (user_id),

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE DATABASE IF NOT EXISTS `artisanats_com`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `artisanats_com`;

CREATE TABLE IF NOT EXISTS craftsmen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    craftsman_id VARCHAR(30) UNIQUE NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    phone VARCHAR(30) NOT NULL,
    password VARCHAR(255) NOT NULL,
    city VARCHAR(80) DEFAULT '',
    profession VARCHAR(80) DEFAULT '',
    specialization VARCHAR(80) DEFAULT '',
    experience_years INT DEFAULT 0,
    experience_label VARCHAR(20) DEFAULT '',
    date_of_birth DATE NULL,
    gender ENUM('male', 'female') NULL,
    grade TINYINT NULL,
    bio TEXT,
    excerpt TEXT,
    avatar VARCHAR(255) DEFAULT '',
    profile_image VARCHAR(255) DEFAULT '',
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    badge_type VARCHAR(50) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS artisan_portfolio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    craftsman_id INT NOT NULL,
    media_type ENUM('image', 'video', 'document') NOT NULL DEFAULT 'image',
    media_url VARCHAR(255) NOT NULL,
    title VARCHAR(200) DEFAULT NULL,
    description TEXT,
    location VARCHAR(100) DEFAULT NULL,
    work_date DATE DEFAULT NULL,
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_portfolio_craftsman
        FOREIGN KEY (craftsman_id)
        REFERENCES craftsmen(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    craftsman_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INT DEFAULT 0,
    mime_type VARCHAR(120) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_documents_craftsman
        FOREIGN KEY (craftsman_id)
        REFERENCES craftsmen(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    craftsman_id INT NOT NULL,
    grade_label VARCHAR(120) NOT NULL,
    grade_value DECIMAL(5,2) DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_grades_craftsman
        FOREIGN KEY (craftsman_id)
        REFERENCES craftsmen(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) DEFAULT NULL,
    role VARCHAR(50) DEFAULT 'admin',
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_user
        FOREIGN KEY (user_id)
        REFERENCES craftsmen(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_type ENUM('admin', 'craftsman') NOT NULL,
    sender_id INT NOT NULL,
    receiver_type ENUM('admin', 'craftsman') NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    message_text TEXT NOT NULL,
    attachment_path VARCHAR(255) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    attachment_mime VARCHAR(120) DEFAULT NULL,
    attachment_size INT DEFAULT NULL,
    attachment_type ENUM('image', 'pdf') DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_receiver (receiver_type, receiver_id),
    INDEX idx_sender (sender_type, sender_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content TEXT,
    category VARCHAR(100) DEFAULT 'عام',
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    duration_minutes INT DEFAULT 0,
    order_num INT DEFAULT 0,
    is_published TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lesson_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    craftsman_id INT NOT NULL,
    progress_percent INT DEFAULT 0,
    is_completed TINYINT(1) DEFAULT 0,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_enrollment_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_craftsman FOREIGN KEY (craftsman_id) REFERENCES craftsmen(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (lesson_id, craftsman_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

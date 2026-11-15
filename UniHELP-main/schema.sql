-- ============================================================
--  UniHELP Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS unihelp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unihelp;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id    VARCHAR(20)  NOT NULL UNIQUE,
    full_name     VARCHAR(120) NOT NULL,
    email         VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(30)  DEFAULT NULL,
    photo         VARCHAR(255) DEFAULT 'assets/default-avatar.png',
    is_volunteer  TINYINT(1)   NOT NULL DEFAULT 0,
    role          ENUM('student','volunteer') NOT NULL DEFAULT 'student',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Two-Factor Codes ──────────────────────────────────────────
CREATE TABLE two_factor_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    code       CHAR(6)      NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Volunteer Skills ──────────────────────────────────────────
CREATE TABLE volunteer_skills (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    skill_type ENUM('academic','technology','new_student','general') NOT NULL,
    UNIQUE KEY uq_user_skill (user_id, skill_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Volunteer Weekly Availability ────────────────────────────
CREATE TABLE volunteer_availability (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    day_of_week ENUM('mon','tue','wed','thu','fri','sat') NOT NULL,
    time_slot  ENUM('08_10','10_12','12_14','14_16','16_18','18_20') NOT NULL,
    UNIQUE KEY uq_user_availability (user_id, day_of_week, time_slot),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Support Requests ──────────────────────────────────────────
CREATE TABLE support_requests (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id         INT UNSIGNED NOT NULL,
    category           ENUM('academic','technology','new_student','general') NOT NULL,
    description        TEXT         NOT NULL,
    preferred_datetime DATETIME     DEFAULT NULL,
    preferred_mode     ENUM('virtual','f2f') NOT NULL DEFAULT 'virtual',
    selected_volunteer_id INT UNSIGNED DEFAULT NULL,
    status             ENUM('open','selected','accepted','rejected','completed','cancelled') NOT NULL DEFAULT 'open',
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_volunteer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Sessions ──────────────────────────────────────────────────
CREATE TABLE sessions (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id         INT UNSIGNED NOT NULL UNIQUE,
    volunteer_id       INT UNSIGNED NOT NULL,
    student_id         INT UNSIGNED NOT NULL,
    scheduled_datetime DATETIME     NOT NULL,
    mode               ENUM('virtual','f2f') NOT NULL DEFAULT 'virtual',
    meeting_details    VARCHAR(255) NOT NULL,
    status             ENUM('upcoming','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id)   REFERENCES support_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Post-Session Feedback ─────────────────────────────────────
CREATE TABLE session_feedback (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL UNIQUE,
    author_id  INT UNSIGNED NOT NULL,
    rating     TINYINT UNSIGNED NOT NULL,
    tags       VARCHAR(255) DEFAULT NULL,
    comment    TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_feedback_rating CHECK (rating BETWEEN 1 AND 5),
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Seed Demo Data ────────────────────────────────────────────
INSERT INTO users (student_id, full_name, email, password_hash, phone, is_volunteer, role) VALUES
('S20241001', 'Alexandra Chen',  'alex.chen@university.edu',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+60 12-345 6789', 0, 'student'),
('S20241002', 'James Lim',       'james.lim@university.edu',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+60 11-234 5678', 1, 'volunteer'),
('S20241003', 'Priya Nair',      'priya.nair@university.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+60 16-789 0123', 1, 'volunteer'),
('S20241004', 'Marcus Wong',     'marcus.wong@university.edu',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL,              1, 'volunteer'),
('S20241005', 'Tan Wei Ming',    'wei.ming@university.edu',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+60 13-456 7890', 0, 'student');
-- Default password for all demo users: "password"

INSERT INTO volunteer_skills (user_id, skill_type) VALUES
(2,'academic'),(2,'general'),
(3,'technology'),(3,'academic'),
(4,'new_student'),(4,'general');

INSERT INTO volunteer_availability (user_id, day_of_week, time_slot) VALUES
(2,'mon','14_16'),(2,'wed','14_16'),(2,'fri','16_18'),
(3,'tue','10_12'),(3,'thu','12_14'),(3,'sat','10_12'),
(4,'mon','16_18'),(4,'wed','16_18'),(4,'sat','14_16');

INSERT INTO support_requests (student_id, category, description, preferred_datetime, preferred_mode, status) VALUES
(5, 'academic',    'Need help with Calculus II — integration by parts is confusing me.', '2026-10-03 14:00:00', 'virtual', 'open'),
(5, 'technology',  'My laptop won\'t boot after a Windows update. Need urgent help!',    '2026-10-04 10:00:00', 'f2f', 'open'),
(1, 'new_student', 'Just arrived on campus, need guidance on registration.',             '2026-10-05 15:00:00', 'virtual', 'open');

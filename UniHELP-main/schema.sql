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
    role          ENUM('student','volunteer','admin') NOT NULL DEFAULT 'student',
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

-- ── Volunteer Availability ────────────────────────────────────
CREATE TABLE volunteer_availability (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id   INT UNSIGNED NOT NULL,
    day_name  ENUM('Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL,
    time_slot VARCHAR(20) NOT NULL,
    UNIQUE KEY uq_user_slot (user_id, day_name, time_slot),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Support Requests ──────────────────────────────────────────
CREATE TABLE support_requests (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id         INT UNSIGNED NOT NULL,
    category           ENUM('academic','technology','new_student','general') NOT NULL,
    description        TEXT         NOT NULL,
    preferred_datetime DATETIME     DEFAULT NULL,
    mode               ENUM('Virtual','F2F') NOT NULL DEFAULT 'Virtual',
    status             ENUM('open','matched','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Sessions ──────────────────────────────────────────────────
CREATE TABLE sessions (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id         INT UNSIGNED NOT NULL,
    volunteer_id       INT UNSIGNED NOT NULL,
    student_id         INT UNSIGNED NOT NULL,
    scheduled_datetime DATETIME     NOT NULL,
    mode               ENUM('Virtual','F2F') NOT NULL DEFAULT 'Virtual',
    location           VARCHAR(255) DEFAULT NULL,
    meeting_link       VARCHAR(255) DEFAULT NULL,
    status             ENUM('upcoming','completed','cancelled','rescheduled') NOT NULL DEFAULT 'upcoming',
    notes              TEXT         DEFAULT NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id)   REFERENCES support_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Feedback ──────────────────────────────────────────────────
CREATE TABLE feedback (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id  INT UNSIGNED NOT NULL UNIQUE,
    reviewer_id INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT         DEFAULT NULL,
    tags        VARCHAR(500) DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id)  REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Notifications ─────────────────────────────────────────────
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    message    VARCHAR(500) NOT NULL,
    link       VARCHAR(255) DEFAULT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

INSERT INTO volunteer_availability (user_id, day_name, time_slot) VALUES
(2,'Mon','2–4pm'),(2,'Mon','4–6pm'),(2,'Wed','2–4pm'),(2,'Wed','4–6pm'),
(3,'Tue','10–12pm'),(3,'Thu','10–12pm'),(3,'Tue','12–2pm'),
(4,'Mon','8–10am'),(4,'Wed','8–10am'),(4,'Fri','8–10am');

INSERT INTO support_requests (student_id, category, description, preferred_datetime, mode, status) VALUES
(5, 'academic',    'Need help with Calculus II — integration by parts is confusing me.', '2026-10-03 14:00:00', 'Virtual', 'open'),
(5, 'technology',  'My laptop won\'t boot after a Windows update. Need urgent help!',    '2026-10-04 10:00:00', 'F2F',     'open'),
(1, 'new_student', 'Just arrived on campus, need guidance on registration.',             '2026-10-05 15:00:00', 'Virtual', 'open');

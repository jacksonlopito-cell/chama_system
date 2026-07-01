-- ============================================================
-- Migration 006: Homepage CMS (Normalized Schema)
-- Replaces JSON-blob page_sections with proper relational tables.
-- ============================================================

-- Drop old JSON-based table if exists (data is migrated out)
DROP TABLE IF EXISTS page_sections;

-- ============================================================
-- 1. Main sections table
-- ============================================================
CREATE TABLE IF NOT EXISTS homepage_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) DEFAULT NULL,
    is_visible TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Section fields (key-value, one row per field)
-- ============================================================
CREATE TABLE IF NOT EXISTS homepage_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    field_value LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE,
    UNIQUE KEY uk_section_field (section_id, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Repeatable items (services, testimonials, features, etc.)
-- ============================================================
CREATE TABLE IF NOT EXISTS homepage_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. Item fields (key-value for each item)
-- ============================================================
CREATE TABLE IF NOT EXISTS homepage_item_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    field_value LONGTEXT,
    FOREIGN KEY (item_id) REFERENCES homepage_items(id) ON DELETE CASCADE,
    UNIQUE KEY uk_item_field (item_id, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. Revision history for undo support
-- ============================================================
CREATE TABLE IF NOT EXISTS homepage_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    snapshot JSON NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

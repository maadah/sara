-- ============================================================
-- Leads & Customer Profiles - Production Migration Script
-- Run this in phpMyAdmin on the production database
-- ============================================================

-- Step 1: Add missing columns to conversations (if not already there)
ALTER TABLE `conversations`
    ADD COLUMN IF NOT EXISTS `lead_id` BIGINT UNSIGNED NULL AFTER `user_id`,
    ADD COLUMN IF NOT EXISTS `ai_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `ai_context` JSON NULL;

-- Add lead_id foreign key only if it doesn't exist
-- (safe to run even if already added)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'conversations'
      AND CONSTRAINT_NAME = 'conversations_lead_id_foreign'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
-- Note: FK will be added below after leads table is created

-- Update platform ENUM to include whatsapp
ALTER TABLE `conversations`
    MODIFY COLUMN `platform` ENUM('facebook','instagram','whatsapp') NOT NULL DEFAULT 'facebook';

-- Step 2: Create leads table
CREATE TABLE IF NOT EXISTS `leads` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         BIGINT UNSIGNED NOT NULL,
    `conversation_id` BIGINT UNSIGNED NULL,
    `name`            VARCHAR(255) NULL,
    `phone`           VARCHAR(255) NULL,
    `whatsapp`        VARCHAR(255) NULL,
    `email`           VARCHAR(255) NULL,
    `address`         TEXT NULL,
    `city`            VARCHAR(255) NULL,
    `area`            VARCHAR(255) NULL,
    `source`          ENUM('facebook','instagram','whatsapp','manual') NOT NULL DEFAULT 'facebook',
    `platform_user_id` VARCHAR(255) NULL,
    `status`          ENUM('new','contacted','interested','converted','lost') NOT NULL DEFAULT 'new',
    `interest_score`  INT NOT NULL DEFAULT 0,
    `total_messages`  INT NOT NULL DEFAULT 0,
    `total_orders`    INT NOT NULL DEFAULT 0,
    `total_spent`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `first_contact_at` TIMESTAMP NULL,
    `last_contact_at`  TIMESTAMP NULL,
    `notes`           TEXT NULL,
    `interests`       JSON NULL,
    `meta_data`       JSON NULL,
    `created_at`      TIMESTAMP NULL,
    `updated_at`      TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `leads_user_id_status_index` (`user_id`, `status`),
    INDEX `leads_user_id_source_index` (`user_id`, `source`),
    INDEX `leads_platform_user_id_index` (`platform_user_id`),
    CONSTRAINT `leads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `leads_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Add FK from conversations.lead_id → leads (now that leads table exists)
ALTER TABLE `conversations`
    ADD CONSTRAINT `conversations_lead_id_foreign`
    FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL;

-- (Ignore error "Duplicate key name" if FK already exists – that means it's already done)

-- Step 4: Create customer_profiles table
CREATE TABLE IF NOT EXISTS `customer_profiles` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`      BIGINT UNSIGNED NOT NULL,
    `lead_id`       BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(255) NULL,
    `phone`         VARCHAR(20) NULL,
    `address`       TEXT NULL,
    `city`          VARCHAR(100) NULL,
    `notes`         TEXT NULL,
    `tags`          JSON NULL,
    `lead_score`    INT UNSIGNED NOT NULL DEFAULT 0,
    `total_orders`  INT UNSIGNED NOT NULL DEFAULT 0,
    `last_order_at` TIMESTAMP NULL,
    `preferences`   JSON NULL,
    -- Demographics
    `age`            TINYINT UNSIGNED NULL,
    `gender`         VARCHAR(10) NULL,
    `budget_min`     INT UNSIGNED NULL,
    `budget_max`     INT UNSIGNED NULL,
    `occupation`     VARCHAR(120) NULL,
    `marital_status` VARCHAR(20) NULL,
    `interests`      JSON NULL,
    `social_platform` VARCHAR(30) NULL,
    `created_at`    TIMESTAMP NULL,
    `updated_at`    TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `customer_profiles_store_id_lead_id_unique` (`store_id`, `lead_id`),
    INDEX `customer_profiles_lead_score_index` (`lead_score`),
    CONSTRAINT `customer_profiles_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `customer_profiles_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Record migrations as run in Laravel's migrations table
INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
    ('2025_12_06_000005_add_ai_fields_to_conversations_table', 99),
    ('2025_12_06_000002_create_leads_table', 99),
    ('2026_02_19_000003_create_customer_profiles_table', 99),
    ('2026_02_20_000001_add_demographics_to_customer_profiles_table', 99),
    ('2026_03_18_000001_add_whatsapp_to_conversations_platform', 99);

SELECT 'Done! Tables created successfully.' AS result;

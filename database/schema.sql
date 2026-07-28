-- ============================================================================
-- Krishna WhatsApp Cloud — Base Schema (fresh install)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- ============================================================================
-- SYSTEM
-- ============================================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(191) NOT NULL UNIQUE,
  `value` LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL UNIQUE,
  `batch` INT NOT NULL DEFAULT 1,
  `duration_ms` INT NOT NULL DEFAULT 0,
  `executed_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `queue` VARCHAR(64) NOT NULL DEFAULT 'default',
  `job_class` VARCHAR(191) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `priority` TINYINT NOT NULL DEFAULT 5,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `max_attempts` TINYINT NOT NULL DEFAULT 3,
  `available_at` INT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL,
  `reserved_by` VARCHAR(64) NULL,
  `created_at` INT UNSIGNED NOT NULL,
  INDEX `idx_pop` (`queue`, `reserved_at`, `available_at`, `priority`),
  INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `queue` VARCHAR(64) NOT NULL,
  `job_class` VARCHAR(191) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NULL,
  `failed_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_failed_at` (`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `channel` VARCHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `type` VARCHAR(64) NOT NULL,
  `payload` JSON NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_stream` (`tenant_id`, `channel`, `id`),
  INDEX `idx_prune` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `type` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NULL,
  `link` VARCHAR(500) NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_user_unread` (`user_id`, `read_at`),
  INDEX `idx_tenant` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `type` ENUM('info','warning','success','danger') NOT NULL DEFAULT 'info',
  `show_banner` TINYINT(1) NOT NULL DEFAULT 1,
  `send_email` TINYINT(1) NOT NULL DEFAULT 0,
  `starts_at` DATETIME NULL,
  `ends_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_window` (`starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `subject` VARCHAR(255) NOT NULL,
  `category` VARCHAR(64) NOT NULL DEFAULT 'general',
  `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` ENUM('open','answered','customer_reply','closed') NOT NULL DEFAULT 'open',
  `last_reply_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  INDEX `idx_status` (`status`, `last_reply_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `is_staff` TINYINT(1) NOT NULL DEFAULT 0,
  `body` TEXT NOT NULL,
  `attachment` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_ticket` (`ticket_id`, `created_at`),
  CONSTRAINT `fk_ticket_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(191) NOT NULL,
  `subject_type` VARCHAR(100) NULL,
  `subject_id` BIGINT UNSIGNED NULL,
  `meta` JSON NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant_created` (`tenant_id`, `created_at`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `error_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `level` VARCHAR(20) NOT NULL DEFAULT 'error',
  `message` TEXT NOT NULL,
  `file` VARCHAR(500) NULL,
  `line` INT NULL,
  `context` JSON NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_created` (`created_at`),
  INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cron_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task` VARCHAR(191) NOT NULL,
  `status` ENUM('success','error') NOT NULL DEFAULT 'success',
  `message` TEXT NULL,
  `duration_ms` INT NOT NULL DEFAULT 0,
  `ran_at` DATETIME NOT NULL,
  INDEX `idx_task` (`task`, `ran_at`),
  INDEX `idx_ran_at` (`ran_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `update_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `from_version` VARCHAR(20) NOT NULL,
  `to_version` VARCHAR(20) NOT NULL,
  `commit_sha` VARCHAR(64) NULL,
  `commit_message` TEXT NULL,
  `files_changed` INT NOT NULL DEFAULT 0,
  `migrations_run` INT NOT NULL DEFAULT 0,
  `duration_seconds` INT NOT NULL DEFAULT 0,
  `status` ENUM('success','failed','rolled_back') NOT NULL,
  `error` TEXT NULL,
  `backup_file_path` VARCHAR(500) NULL,
  `backup_db_path` VARCHAR(500) NULL,
  `triggered_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('files','database') NOT NULL,
  `trigger_type` ENUM('manual','scheduled','pre_update') NOT NULL DEFAULT 'manual',
  `path` VARCHAR(500) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('completed','failed','deleted') NOT NULL DEFAULT 'completed',
  `created_at` DATETIME NOT NULL,
  INDEX `idx_type` (`type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media_library` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `uploaded_by` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `path` VARCHAR(500) NOT NULL,
  `mime` VARCHAR(100) NOT NULL,
  `type` ENUM('image','video','audio','document','sticker','other') NOT NULL DEFAULT 'other',
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `meta_media_id` VARCHAR(191) NULL,
  `meta_media_expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `created_at`),
  INDEX `idx_meta_media` (`meta_media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stats_daily` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `metric` VARCHAR(64) NOT NULL,
  `value` DECIMAL(18,4) NOT NULL DEFAULT 0,
  UNIQUE KEY `uq_stat` (`tenant_id`, `date`, `metric`),
  INDEX `idx_metric` (`metric`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TENANCY & BILLING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `price_monthly` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `price_yearly` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `trial_days` INT NOT NULL DEFAULT 7,
  `billing_type` ENUM('subscription','payg') NOT NULL DEFAULT 'subscription',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_features` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plan_id` INT UNSIGNED NOT NULL,
  `feature` VARCHAR(64) NOT NULL,
  `value` VARCHAR(64) NULL,
  UNIQUE KEY `uq_plan_feature` (`plan_id`, `feature`),
  CONSTRAINT `fk_plan_features_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `plan_id` INT UNSIGNED NULL,
  `parent_tenant_id` BIGINT UNSIGNED NULL,
  `is_reseller` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','suspended','pending','deleted') NOT NULL DEFAULT 'active',
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
  `country` CHAR(2) NOT NULL DEFAULT 'IN',
  `state_code` VARCHAR(10) NULL,
  `gstin` VARCHAR(20) NULL,
  `billing_name` VARCHAR(191) NULL,
  `billing_address` TEXT NULL,
  `trial_ends_at` DATETIME NULL,
  `subscription_ends_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_plan` (`plan_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_parent` (`parent_tenant_id`),
  CONSTRAINT `fk_tenants_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `key` VARCHAR(191) NOT NULL,
  `value` LONGTEXT NULL,
  UNIQUE KEY `uq_tenant_key` (`tenant_id`, `key`),
  CONSTRAINT `fk_tenant_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_domains` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `domain` VARCHAR(191) NOT NULL UNIQUE,
  `status` ENUM('pending','verified','failed') NOT NULL DEFAULT 'pending',
  `verification_token` VARCHAR(64) NOT NULL,
  `verified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_tenant_domains_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `billing_cycle` ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `status` ENUM('active','trialing','past_due','cancelled','expired') NOT NULL DEFAULT 'active',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `gateway` VARCHAR(32) NULL,
  `gateway_subscription_id` VARCHAR(191) NULL,
  `current_period_start` DATETIME NULL,
  `current_period_end` DATETIME NULL,
  `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0,
  `cancelled_at` DATETIME NULL,
  `dunning_attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  INDEX `idx_period_end` (`current_period_end`),
  CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscription_usage` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `feature` VARCHAR(64) NOT NULL,
  `period` CHAR(7) NOT NULL,
  `used` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NULL,
  UNIQUE KEY `uq_usage` (`tenant_id`, `feature`, `period`),
  CONSTRAINT `fk_subscription_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `invoice_number` VARCHAR(64) NOT NULL UNIQUE,
  `status` ENUM('draft','unpaid','paid','void','refunded') NOT NULL DEFAULT 'unpaid',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `taxable_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `cgst` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `sgst` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `igst` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `gstin` VARCHAR(20) NULL,
  `hsn_sac` VARCHAR(20) NOT NULL DEFAULT '998314',
  `place_of_supply` VARCHAR(64) NULL,
  `billing_details` JSON NULL,
  `due_at` DATETIME NULL,
  `paid_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  INDEX `idx_created` (`created_at`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  INDEX `idx_invoice` (`invoice_id`),
  CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED NULL,
  `gateway` VARCHAR(32) NOT NULL,
  `gateway_txn_id` VARCHAR(191) NULL,
  `type` ENUM('payment','refund','wallet_recharge') NOT NULL DEFAULT 'payment',
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `status` ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `meta` JSON NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_gateway_txn` (`gateway`, `gateway_txn_id`),
  INDEX `idx_tenant` (`tenant_id`, `created_at`),
  CONSTRAINT `fk_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallets` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `balance` DECIMAL(14,4) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `low_balance_threshold` DECIMAL(12,2) NOT NULL DEFAULT 100,
  `low_balance_alerted_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL,
  CONSTRAINT `fk_wallets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('credit','debit') NOT NULL,
  `amount` DECIMAL(14,4) NOT NULL,
  `balance_after` DECIMAL(14,4) NOT NULL,
  `reason` VARCHAR(191) NOT NULL,
  `reference_type` VARCHAR(64) NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `created_at`),
  CONSTRAINT `fk_wallet_txns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupons` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(64) NOT NULL UNIQUE,
  `type` ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(12,2) NOT NULL,
  `max_discount` DECIMAL(12,2) NULL,
  `first_time_only` TINYINT(1) NOT NULL DEFAULT 0,
  `plan_ids` JSON NULL,
  `usage_cap` INT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `expires_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `coupon_id` INT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED NULL,
  `discount_amount` DECIMAL(12,2) NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_coupon` (`coupon_id`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_coupon_redemptions_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taxes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `country` CHAR(2) NOT NULL DEFAULT 'IN',
  `state_code` VARCHAR(10) NULL,
  `rate_percent` DECIMAL(5,2) NOT NULL DEFAULT 18,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  INDEX `idx_country` (`country`, `state_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `referrals` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `referrer_tenant_id` BIGINT UNSIGNED NOT NULL,
  `referred_tenant_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(32) NOT NULL,
  `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 10,
  `status` ENUM('pending','qualified','paid') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_referred` (`referred_tenant_id`),
  INDEX `idx_referrer` (`referrer_tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `referral_payouts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('requested','approved','paid','rejected') NOT NULL DEFAULT 'requested',
  `payout_details` JSON NULL,
  `processed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `white_label_settings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `brand_name` VARCHAR(191) NULL,
  `logo_path` VARCHAR(500) NULL,
  `favicon_path` VARCHAR(500) NULL,
  `primary_color` VARCHAR(9) NULL,
  `accent_color` VARCHAR(9) NULL,
  `hide_powered_by` TINYINT(1) NOT NULL DEFAULT 0,
  `custom_login_html` MEDIUMTEXT NULL,
  `custom_smtp` JSON NULL,
  `custom_css` MEDIUMTEXT NULL,
  `updated_at` DATETIME NOT NULL,
  CONSTRAINT `fk_white_label_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- USERS & ACCESS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_slug` (`tenant_id`, `slug`),
  INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `group_name` VARCHAR(64) NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `role_id` INT UNSIGNED NULL,
  `name` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NULL,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(500) NULL,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
  `locale` VARCHAR(10) NULL,
  `timezone` VARCHAR(64) NULL,
  `dark_mode` TINYINT(1) NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `two_factor_secret` VARCHAR(255) NULL,
  `two_factor_whatsapp` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_backup_codes` TEXT NULL,
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  INDEX `idx_role` (`role_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `permission_id`),
  CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(9) NULL,
  `working_hours` JSON NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_departments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `department_users` (
  `department_id` INT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`department_id`, `user_id`),
  CONSTRAINT `fk_department_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_department_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `selector` VARCHAR(32) NOT NULL UNIQUE,
  `token_hash` VARCHAR(64) NOT NULL,
  `type` ENUM('remember','api') NOT NULL DEFAULT 'remember',
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expires` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `reason` VARCHAR(64) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_email` (`email`, `created_at`),
  INDEX `idx_ip` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `passkeys` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `credential_id` VARCHAR(500) NOT NULL,
  `public_key` TEXT NOT NULL,
  `sign_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_user` (`user_id`),
  CONSTRAINT `fk_passkeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- WHATSAPP CORE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `meta_apps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL DEFAULT 'Default',
  `app_id` VARCHAR(64) NOT NULL,
  `app_secret_encrypted` TEXT NOT NULL,
  `config_id` VARCHAR(64) NULL,
  `system_user_token_encrypted` TEXT NULL,
  `api_version` VARCHAR(10) NOT NULL DEFAULT 'v21.0',
  `webhook_verify_token` VARCHAR(191) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `waba_accounts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `meta_app_id` INT UNSIGNED NULL,
  `waba_id` VARCHAR(64) NOT NULL,
  `name` VARCHAR(191) NULL,
  `currency` CHAR(3) NULL,
  `timezone_id` VARCHAR(10) NULL,
  `token_mode` ENUM('embedded_signup','permanent_system_user','temporary_manual') NOT NULL DEFAULT 'embedded_signup',
  `access_token_encrypted` TEXT NOT NULL,
  `token_expires_at` DATETIME NULL,
  `token_expiry_warned_at` DATETIME NULL,
  `status` ENUM('active','disconnected','error') NOT NULL DEFAULT 'active',
  `subscribed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_waba` (`tenant_id`, `waba_id`),
  INDEX `idx_waba` (`waba_id`),
  CONSTRAINT `fk_waba_accounts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `phone_numbers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `waba_account_id` BIGINT UNSIGNED NOT NULL,
  `phone_number_id` VARCHAR(64) NOT NULL,
  `display_phone_number` VARCHAR(32) NOT NULL,
  `verified_name` VARCHAR(191) NULL,
  `quality_rating` VARCHAR(20) NULL,
  `code_verification_status` VARCHAR(32) NULL,
  `throughput_level` VARCHAR(32) NULL,
  `messaging_limit_tier` VARCHAR(32) NULL,
  `pin_encrypted` TEXT NULL,
  `is_registered` TINYINT(1) NOT NULL DEFAULT 0,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive','flagged','banned') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_phone_number_id` (`phone_number_id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_waba` (`waba_account_id`),
  CONSTRAINT `fk_phone_numbers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_phone_numbers_waba` FOREIGN KEY (`waba_account_id`) REFERENCES `waba_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `templates` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `waba_account_id` BIGINT UNSIGNED NOT NULL,
  `meta_template_id` VARCHAR(64) NULL,
  `name` VARCHAR(191) NOT NULL,
  `language` VARCHAR(10) NOT NULL DEFAULT 'en',
  `category` ENUM('MARKETING','UTILITY','AUTHENTICATION') NOT NULL DEFAULT 'MARKETING',
  `status` ENUM('DRAFT','PENDING','APPROVED','REJECTED','PAUSED','DISABLED') NOT NULL DEFAULT 'DRAFT',
  `rejected_reason` TEXT NULL,
  `components` JSON NOT NULL,
  `variable_mapping` JSON NULL,
  `last_synced_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_waba_name_lang` (`waba_account_id`, `name`, `language`),
  INDEX `idx_tenant` (`tenant_id`, `status`),
  CONSTRAINT `fk_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_templates_waba` FOREIGN KEY (`waba_account_id`) REFERENCES `waba_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `template_versions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `components` JSON NOT NULL,
  `status` VARCHAR(20) NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_template` (`template_id`),
  CONSTRAINT `fk_template_versions_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contacts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `name` VARCHAR(191) NULL,
  `email` VARCHAR(191) NULL,
  `avatar` VARCHAR(500) NULL,
  `country_code` VARCHAR(5) NULL,
  `language` VARCHAR(10) NULL,
  `lifecycle_stage` VARCHAR(32) NOT NULL DEFAULT 'lead',
  `lead_score` INT NOT NULL DEFAULT 0,
  `source` VARCHAR(64) NULL,
  `opt_in` TINYINT(1) NOT NULL DEFAULT 1,
  `opt_out_at` DATETIME NULL,
  `opt_in_log` JSON NULL,
  `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
  `last_message_at` DATETIME NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_phone` (`tenant_id`, `phone`),
  INDEX `idx_tenant_created` (`tenant_id`, `created_at`),
  INDEX `idx_lifecycle` (`tenant_id`, `lifecycle_stage`),
  FULLTEXT KEY `ft_search` (`name`, `phone`, `email`),
  CONSTRAINT `fk_contacts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_fields` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  `type` ENUM('text','number','date','dropdown','checkbox','file') NOT NULL DEFAULT 'text',
  `options` JSON NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_key` (`tenant_id`, `key`),
  CONSTRAINT `fk_contact_fields_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_field_values` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED NOT NULL,
  `value` TEXT NULL,
  UNIQUE KEY `uq_contact_field` (`contact_id`, `field_id`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_field` (`field_id`),
  CONSTRAINT `fk_cfv_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cfv_field` FOREIGN KEY (`field_id`) REFERENCES `contact_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(9) NOT NULL DEFAULT '#0F766E',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_name` (`tenant_id`, `name`),
  CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_tags` (
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`contact_id`, `tag_id`),
  INDEX `idx_tag` (`tag_id`),
  CONSTRAINT `fk_contact_tags_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contact_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `contact_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_groups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_contacts` (
  `group_id` INT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`group_id`, `contact_id`),
  INDEX `idx_contact` (`contact_id`),
  CONSTRAINT `fk_group_contacts_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_contacts_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `segments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `rules` JSON NOT NULL,
  `contact_count` INT NOT NULL DEFAULT 0,
  `refreshed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_segments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `phone_number_id` BIGINT UNSIGNED NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `status` ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `is_starred` TINYINT(1) NOT NULL DEFAULT 0,
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `unread_count` INT NOT NULL DEFAULT 0,
  `last_message_id` BIGINT UNSIGNED NULL,
  `last_message_preview` VARCHAR(255) NULL,
  `last_message_at` DATETIME NULL,
  `last_inbound_at` DATETIME NULL,
  `last_outbound_at` DATETIME NULL,
  `session_expires_at` DATETIME NULL,
  `session_open` TINYINT(1) NOT NULL DEFAULT 0,
  `sentiment` ENUM('positive','neutral','negative','angry') NULL,
  `ai_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `sla_due_at` DATETIME NULL,
  `sla_breached` TINYINT(1) NOT NULL DEFAULT 0,
  `closed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_contact_phone` (`tenant_id`, `contact_id`, `phone_number_id`),
  INDEX `idx_inbox_list` (`tenant_id`, `is_archived`, `status`, `last_message_at`),
  INDEX `idx_assigned` (`tenant_id`, `assigned_to`, `status`),
  INDEX `idx_session` (`session_open`, `session_expires_at`),
  CONSTRAINT `fk_conversations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `phone_number_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `campaign_id` BIGINT UNSIGNED NULL,
  `flow_run_id` BIGINT UNSIGNED NULL,
  `wamid` VARCHAR(191) NULL,
  `direction` ENUM('in','out') NOT NULL,
  `type` VARCHAR(32) NOT NULL DEFAULT 'text',
  `body` MEDIUMTEXT NULL,
  `media_path` VARCHAR(500) NULL,
  `media_mime` VARCHAR(100) NULL,
  `media_meta_id` VARCHAR(191) NULL,
  `payload` JSON NULL,
  `status` ENUM('queued','sent','delivered','read','failed','deleted') NOT NULL DEFAULT 'queued',
  `pricing_category` ENUM('marketing','utility','authentication','service','referral_conversion','free') NULL,
  `pricing_model` VARCHAR(20) NULL,
  `cost` DECIMAL(12,6) NULL,
  `currency` CHAR(3) NULL,
  `conversation_meta_id` VARCHAR(191) NULL,
  `error_code` INT NULL,
  `error_title` VARCHAR(255) NULL,
  `context_wamid` VARCHAR(191) NULL,
  `referral` JSON NULL,
  `is_private_note` TINYINT(1) NOT NULL DEFAULT 0,
  `sent_at` DATETIME NULL,
  `delivered_at` DATETIME NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_wamid` (`wamid`),
  INDEX `idx_conversation` (`conversation_id`, `id`),
  INDEX `idx_tenant_created` (`tenant_id`, `created_at`),
  INDEX `idx_contact` (`contact_id`),
  INDEX `idx_campaign` (`campaign_id`),
  INDEX `idx_status` (`tenant_id`, `status`),
  CONSTRAINT `fk_messages_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `message_status_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `wamid` VARCHAR(191) NOT NULL,
  `status` VARCHAR(20) NOT NULL,
  `error_code` INT NULL,
  `raw` JSON NULL,
  `occurred_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_wamid_status` (`wamid`, `status`),
  INDEX `idx_message` (`message_id`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_msl_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pricing_rates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `country_code` VARCHAR(5) NOT NULL,
  `country_name` VARCHAR(100) NULL,
  `category` ENUM('marketing','utility','authentication','service') NOT NULL,
  `rate` DECIMAL(12,6) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `effective_from` DATE NOT NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_rate` (`country_code`, `category`, `effective_from`),
  INDEX `idx_lookup` (`country_code`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INBOX
-- ============================================================================

CREATE TABLE IF NOT EXISTS `conversation_notes` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `mentions` JSON NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_conversation` (`conversation_id`),
  CONSTRAINT `fk_conv_notes_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `type` VARCHAR(64) NOT NULL,
  `data` JSON NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_conversation` (`conversation_id`, `created_at`),
  CONSTRAINT `fk_conv_events_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quick_replies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `shortcut` VARCHAR(64) NOT NULL,
  `body` TEXT NOT NULL,
  `media_path` VARCHAR(500) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `is_shared` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_shortcut` (`tenant_id`, `shortcut`),
  CONSTRAINT `fk_quick_replies_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `canned_responses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `body` TEXT NOT NULL,
  `category` VARCHAR(64) NULL,
  `media_path` VARCHAR(500) NULL,
  `usage_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_canned_responses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agent_signatures` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `signature` TEXT NOT NULL,
  `auto_append` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_user` (`user_id`),
  CONSTRAINT `fk_agent_signatures_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agent_presence` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('online','away','offline') NOT NULL DEFAULT 'offline',
  `active_conversations` INT NOT NULL DEFAULT 0,
  `last_seen_at` DATETIME NULL,
  UNIQUE KEY `uq_user` (`user_id`),
  INDEX `idx_tenant` (`tenant_id`, `status`),
  CONSTRAINT `fk_agent_presence_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assignment_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `strategy` ENUM('round_robin','least_busy','department','tag','working_hours') NOT NULL DEFAULT 'round_robin',
  `conditions` JSON NULL,
  `target` JSON NULL,
  `priority` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `is_active`),
  CONSTRAINT `fk_assignment_rules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sla_policies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `first_response_minutes` INT NOT NULL DEFAULT 15,
  `resolution_minutes` INT NOT NULL DEFAULT 480,
  `applies_to` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_sla_policies_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_transfers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `from_user_id` BIGINT UNSIGNED NULL,
  `to_user_id` BIGINT UNSIGNED NULL,
  `to_department_id` INT UNSIGNED NULL,
  `reason` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_conversation` (`conversation_id`),
  CONSTRAINT `fk_chat_transfers_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CAMPAIGNS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `phone_number_id` BIGINT UNSIGNED NULL,
  `template_id` BIGINT UNSIGNED NULL,
  `message_type` ENUM('template','text','media') NOT NULL DEFAULT 'template',
  `content` JSON NULL,
  `variable_mapping` JSON NULL,
  `audience_type` ENUM('all','groups','segments','tags','csv','manual') NOT NULL DEFAULT 'all',
  `audience_config` JSON NULL,
  `exclusion_list_ids` JSON NULL,
  `status` ENUM('draft','scheduled','running','paused','completed','cancelled','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at` DATETIME NULL,
  `timezone_aware` TINYINT(1) NOT NULL DEFAULT 0,
  `recurring` ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  `throttle_per_minute` INT NOT NULL DEFAULT 60,
  `respect_frequency_cap` TINYINT(1) NOT NULL DEFAULT 1,
  `total_recipients` INT NOT NULL DEFAULT 0,
  `sent_count` INT NOT NULL DEFAULT 0,
  `delivered_count` INT NOT NULL DEFAULT 0,
  `read_count` INT NOT NULL DEFAULT 0,
  `replied_count` INT NOT NULL DEFAULT 0,
  `failed_count` INT NOT NULL DEFAULT 0,
  `total_cost` DECIMAL(14,4) NOT NULL DEFAULT 0,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  INDEX `idx_scheduled` (`status`, `scheduled_at`),
  CONSTRAINT `fk_campaigns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_recipients` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `ab_variant_id` BIGINT UNSIGNED NULL,
  `message_id` BIGINT UNSIGNED NULL,
  `status` ENUM('pending','queued','sent','delivered','read','replied','failed','skipped') NOT NULL DEFAULT 'pending',
  `error` VARCHAR(255) NULL,
  `cost` DECIMAL(12,6) NULL,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_campaign_contact` (`campaign_id`, `contact_id`),
  INDEX `idx_pop` (`campaign_id`, `status`, `id`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_campaign_recipients_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campaign_recipients_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_stats` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `metric` VARCHAR(32) NOT NULL,
  `value` BIGINT NOT NULL DEFAULT 0,
  UNIQUE KEY `uq_stat` (`campaign_id`, `date`, `metric`),
  CONSTRAINT `fk_campaign_stats_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ab_variants` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(10) NOT NULL,
  `template_id` BIGINT UNSIGNED NULL,
  `content` JSON NULL,
  `split_percent` INT NOT NULL DEFAULT 50,
  `sent_count` INT NOT NULL DEFAULT 0,
  `read_count` INT NOT NULL DEFAULT 0,
  `replied_count` INT NOT NULL DEFAULT 0,
  `is_winner` TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT `fk_ab_variants_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exclusion_lists` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone_numbers` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_exclusion_lists_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `frequency_caps` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `category` VARCHAR(20) NOT NULL DEFAULT 'marketing',
  `sent_count` INT NOT NULL DEFAULT 0,
  `window_started_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_contact_category` (`contact_id`, `category`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_frequency_caps_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AUTOMATION & BOT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `flows` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `description` VARCHAR(500) NULL,
  `definition` LONGTEXT NULL,
  `status` ENUM('draft','active','paused') NOT NULL DEFAULT 'draft',
  `trigger_type` VARCHAR(32) NOT NULL DEFAULT 'keyword',
  `trigger_config` JSON NULL,
  `runs_count` INT NOT NULL DEFAULT 0,
  `version` INT NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  INDEX `idx_trigger` (`tenant_id`, `trigger_type`, `status`),
  CONSTRAINT `fk_flows_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flow_versions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `flow_id` BIGINT UNSIGNED NOT NULL,
  `version` INT NOT NULL,
  `definition` LONGTEXT NOT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_flow_version` (`flow_id`, `version`),
  CONSTRAINT `fk_flow_versions_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flow_nodes` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `flow_id` BIGINT UNSIGNED NOT NULL,
  `node_key` VARCHAR(64) NOT NULL,
  `type` VARCHAR(32) NOT NULL,
  `config` JSON NULL,
  `connections` JSON NULL,
  UNIQUE KEY `uq_flow_node` (`flow_id`, `node_key`),
  CONSTRAINT `fk_flow_nodes_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flow_runs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `flow_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NULL,
  `current_node` VARCHAR(64) NULL,
  `variables` JSON NULL,
  `status` ENUM('running','waiting_reply','waiting_delay','completed','failed','cancelled') NOT NULL DEFAULT 'running',
  `resume_at` DATETIME NULL,
  `wait_timeout_at` DATETIME NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  INDEX `idx_resume` (`status`, `resume_at`),
  INDEX `idx_contact_active` (`contact_id`, `status`),
  CONSTRAINT `fk_flow_runs_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flow_run_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `flow_run_id` BIGINT UNSIGNED NOT NULL,
  `node_key` VARCHAR(64) NOT NULL,
  `node_type` VARCHAR(32) NOT NULL,
  `status` ENUM('ok','error','skipped') NOT NULL DEFAULT 'ok',
  `detail` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_run` (`flow_run_id`),
  CONSTRAINT `fk_flow_run_logs_run` FOREIGN KEY (`flow_run_id`) REFERENCES `flow_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `triggers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `flow_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(32) NOT NULL,
  `config` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant_type` (`tenant_id`, `type`, `is_active`),
  CONSTRAINT `fk_triggers_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `keywords` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `keyword` VARCHAR(191) NOT NULL,
  `match_type` ENUM('exact','contains','starts_with','regex') NOT NULL DEFAULT 'exact',
  `flow_id` BIGINT UNSIGNED NULL,
  `reply_text` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `hit_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `is_active`),
  CONSTRAINT `fk_keywords_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scheduled_actions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(64) NOT NULL,
  `config` JSON NOT NULL,
  `contact_id` BIGINT UNSIGNED NULL,
  `run_at` DATETIME NOT NULL,
  `status` ENUM('pending','done','failed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL,
  INDEX `idx_due` (`status`, `run_at`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_scheduled_actions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AI
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_providers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `provider` VARCHAR(32) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `api_key_encrypted` TEXT NULL,
  `base_url` VARCHAR(255) NULL,
  `default_model` VARCHAR(100) NULL,
  `embedding_model` VARCHAR(100) NULL,
  `is_platform` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `markup_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_agents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `provider_id` INT UNSIGNED NULL,
  `model` VARCHAR(100) NULL,
  `system_prompt` TEXT NULL,
  `persona` VARCHAR(64) NULL,
  `language_lock` VARCHAR(10) NULL,
  `knowledge_base_id` INT UNSIGNED NULL,
  `kb_only_mode` TINYINT(1) NOT NULL DEFAULT 1,
  `memory_turns` INT NOT NULL DEFAULT 10,
  `confidence_threshold` DECIMAL(3,2) NOT NULL DEFAULT 0.60,
  `handover_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `business_hours_only` TINYINT(1) NOT NULL DEFAULT 0,
  `fallback_message` TEXT NULL,
  `max_context_tokens` INT NOT NULL DEFAULT 4000,
  `daily_token_cap_per_conversation` INT NOT NULL DEFAULT 20000,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `is_active`),
  CONSTRAINT `fk_ai_agents_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `knowledge_bases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `chunk_count` INT NOT NULL DEFAULT 0,
  `total_tokens` BIGINT NOT NULL DEFAULT 0,
  `status` ENUM('ready','training','error') NOT NULL DEFAULT 'ready',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_kb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kb_documents` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `knowledge_base_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('pdf','docx','txt','csv','url','faq','manual','chat_history') NOT NULL,
  `source_path` VARCHAR(500) NULL,
  `source_url` VARCHAR(500) NULL,
  `content_hash` VARCHAR(64) NULL,
  `chunk_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending','processing','trained','error') NOT NULL DEFAULT 'pending',
  `error` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_kb` (`knowledge_base_id`, `status`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_kb_documents_kb` FOREIGN KEY (`knowledge_base_id`) REFERENCES `knowledge_bases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kb_chunks` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `knowledge_base_id` INT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `chunk_index` INT NOT NULL DEFAULT 0,
  `heading` VARCHAR(255) NULL,
  `content` TEXT NOT NULL,
  `token_count` INT NOT NULL DEFAULT 0,
  `embedding` LONGTEXT NULL,
  `embedding_norm` DOUBLE NULL,
  `content_hash` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_kb` (`knowledge_base_id`),
  INDEX `idx_document` (`document_id`),
  INDEX `idx_tenant` (`tenant_id`),
  FULLTEXT KEY `ft_content` (`content`),
  CONSTRAINT `fk_kb_chunks_document` FOREIGN KEY (`document_id`) REFERENCES `kb_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_conversations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `ai_agent_id` INT UNSIGNED NOT NULL,
  `summary` TEXT NULL,
  `turns` INT NOT NULL DEFAULT 0,
  `tokens_today` INT NOT NULL DEFAULT 0,
  `tokens_date` DATE NULL,
  `status` ENUM('active','paused','handed_over') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_conversation_agent` (`conversation_id`, `ai_agent_id`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_ai_conversations_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(32) NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `feature` VARCHAR(64) NOT NULL,
  `conversation_id` BIGINT UNSIGNED NULL,
  `input_tokens` INT NOT NULL DEFAULT 0,
  `output_tokens` INT NOT NULL DEFAULT 0,
  `cached_tokens` INT NOT NULL DEFAULT 0,
  `cost` DECIMAL(12,6) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant_created` (`tenant_id`, `created_at`),
  INDEX `idx_feature` (`feature`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_prompts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `feature` VARCHAR(64) NOT NULL,
  `prompt` TEXT NOT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `feature`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_handovers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `ai_agent_id` INT UNSIGNED NULL,
  `reason` VARCHAR(255) NULL,
  `confidence` DECIMAL(3,2) NULL,
  `assigned_to` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `created_at`),
  CONSTRAINT `fk_ai_handovers_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MARKETING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `funnels` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `steps` JSON NULL,
  `stats` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_funnels_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `landing_pages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `sections` JSON NULL,
  `seo_title` VARCHAR(191) NULL,
  `seo_description` VARCHAR(300) NULL,
  `custom_domain` VARCHAR(191) NULL,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `views` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_slug` (`tenant_id`, `slug`),
  CONSTRAINT `fk_landing_pages_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forms` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `settings` JSON NULL,
  `whatsapp_on_submit` TINYINT(1) NOT NULL DEFAULT 0,
  `whatsapp_template_id` BIGINT UNSIGNED NULL,
  `flow_id` BIGINT UNSIGNED NULL,
  `submission_count` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_slug` (`tenant_id`, `slug`),
  CONSTRAINT `fk_forms_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `form_fields` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `form_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(191) NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  `type` VARCHAR(32) NOT NULL DEFAULT 'text',
  `options` JSON NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `map_to_contact_field` VARCHAR(100) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  INDEX `idx_form` (`form_id`),
  CONSTRAINT `fk_form_fields_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `form_submissions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `form_id` INT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NULL,
  `data` JSON NOT NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_form` (`form_id`, `created_at`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_form_submissions_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `popups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `content` JSON NULL,
  `trigger_type` ENUM('exit_intent','delay','scroll') NOT NULL DEFAULT 'exit_intent',
  `trigger_value` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `views` INT NOT NULL DEFAULT 0,
  `conversions` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_popups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qr_codes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('whatsapp','url','review','dynamic') NOT NULL DEFAULT 'whatsapp',
  `slug` VARCHAR(32) NOT NULL UNIQUE,
  `target` TEXT NOT NULL,
  `prefilled_message` VARCHAR(500) NULL,
  `image_path` VARCHAR(500) NULL,
  `scan_count` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_qr_codes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qr_scans` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `qr_code_id` INT UNSIGNED NOT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `country` VARCHAR(5) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_qr` (`qr_code_id`, `created_at`),
  CONSTRAINT `fk_qr_scans_qr` FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `review_requests` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `platform` VARCHAR(32) NOT NULL DEFAULT 'google',
  `review_url` VARCHAR(500) NOT NULL,
  `status` ENUM('sent','clicked','reviewed') NOT NULL DEFAULT 'sent',
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_review_requests_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `affiliates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(32) NOT NULL UNIQUE,
  `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 20,
  `total_earned` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total_paid` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant` (`tenant_id`),
  CONSTRAINT `fk_affiliates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `affiliate_commissions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `affiliate_id` INT UNSIGNED NOT NULL,
  `referred_tenant_id` BIGINT UNSIGNED NOT NULL,
  `invoice_id` BIGINT UNSIGNED NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL,
  INDEX `idx_affiliate` (`affiliate_id`, `status`),
  CONSTRAINT `fk_affiliate_commissions_affiliate` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- E-COMMERCE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `stores` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `platform` ENUM('shopify','woocommerce','magento','opencart','custom') NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `store_url` VARCHAR(255) NOT NULL,
  `credentials_encrypted` TEXT NULL,
  `webhook_secret` VARCHAR(191) NULL,
  `sync_status` ENUM('idle','syncing','error') NOT NULL DEFAULT 'idle',
  `last_synced_at` DATETIME NULL,
  `abandoned_cart_flow_id` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_stores_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_products` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `external_id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(100) NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `stock` INT NULL,
  `image_url` VARCHAR(500) NULL,
  `product_url` VARCHAR(500) NULL,
  `catalog_retailer_id` VARCHAR(100) NULL,
  `data` JSON NULL,
  `updated_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_store_external` (`store_id`, `external_id`),
  INDEX `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_store_products_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NULL,
  `contact_id` BIGINT UNSIGNED NULL,
  `external_id` VARCHAR(100) NULL,
  `order_number` VARCHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `payment_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(32) NULL,
  `is_cod` TINYINT(1) NOT NULL DEFAULT 0,
  `cod_confirmed` TINYINT(1) NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `shipping` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `tax` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `customer_details` JSON NULL,
  `placed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_store_external` (`store_id`, `external_id`),
  INDEX `idx_tenant` (`tenant_id`, `created_at`),
  INDEX `idx_contact` (`contact_id`),
  CONSTRAINT `fk_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  INDEX `idx_order` (`order_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NULL,
  `contact_id` BIGINT UNSIGNED NULL,
  `external_id` VARCHAR(100) NULL,
  `items` JSON NULL,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'INR',
  `checkout_url` VARCHAR(500) NULL,
  `status` ENUM('active','abandoned','recovered','converted') NOT NULL DEFAULT 'active',
  `abandoned_at` DATETIME NULL,
  `recovery_sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant_status` (`tenant_id`, `status`),
  INDEX `idx_abandoned` (`status`, `abandoned_at`),
  CONSTRAINT `fk_carts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `carrier` VARCHAR(64) NULL,
  `tracking_number` VARCHAR(100) NULL,
  `tracking_url` VARCHAR(500) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `shipped_at` DATETIME NULL,
  `delivered_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_order` (`order_id`),
  CONSTRAINT `fk_shipments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NULL,
  `type` VARCHAR(32) NOT NULL,
  `status` ENUM('success','error') NOT NULL,
  `items_synced` INT NOT NULL DEFAULT 0,
  `message` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalogs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `waba_account_id` BIGINT UNSIGNED NULL,
  `catalog_id` VARCHAR(64) NOT NULL,
  `name` VARCHAR(191) NULL,
  `product_count` INT NOT NULL DEFAULT 0,
  `last_synced_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_catalog` (`tenant_id`, `catalog_id`),
  CONSTRAINT `fk_catalogs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INTEGRATIONS & API
-- ============================================================================

CREATE TABLE IF NOT EXISTS `integrations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(64) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `config_encrypted` TEXT NULL,
  `status` ENUM('active','inactive','error') NOT NULL DEFAULT 'active',
  `last_used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant_type` (`tenant_id`, `type`),
  CONSTRAINT `fk_integrations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `key_id` VARCHAR(32) NOT NULL UNIQUE,
  `secret_hash` VARCHAR(64) NOT NULL,
  `scopes` JSON NULL,
  `rate_limit` INT NOT NULL DEFAULT 60,
  `status` ENUM('active','revoked') NOT NULL DEFAULT 'active',
  `last_used_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `status`),
  CONSTRAINT `fk_api_keys_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `api_key_id` BIGINT UNSIGNED NULL,
  `method` VARCHAR(10) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `status_code` INT NULL,
  `duration_ms` INT NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant_created` (`tenant_id`, `created_at`),
  INDEX `idx_key` (`api_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `events` JSON NOT NULL,
  `secret` VARCHAR(191) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `failure_count` INT NOT NULL DEFAULT 0,
  `last_triggered_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_tenant` (`tenant_id`, `is_active`),
  CONSTRAINT `fk_webhooks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NULL,
  `direction` ENUM('in','out') NOT NULL DEFAULT 'in',
  `source` VARCHAR(64) NOT NULL DEFAULT 'meta',
  `webhook_id` INT UNSIGNED NULL,
  `event` VARCHAR(100) NULL,
  `payload` LONGTEXT NULL,
  `response_code` INT NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `status` ENUM('received','processing','processed','failed','delivered') NOT NULL DEFAULT 'received',
  `error` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_source_created` (`source`, `created_at`),
  INDEX `idx_tenant` (`tenant_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_tokens` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(32) NOT NULL,
  `access_token_encrypted` TEXT NOT NULL,
  `refresh_token_encrypted` TEXT NULL,
  `scopes` VARCHAR(500) NULL,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_tenant_provider` (`tenant_id`, `provider`),
  CONSTRAINT `fk_oauth_tokens_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zapier_subscriptions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `event` VARCHAR(100) NOT NULL,
  `target_url` VARCHAR(500) NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_tenant_event` (`tenant_id`, `event`),
  CONSTRAINT `fk_zapier_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Default plans. Feature value -1 (or NULL) = unlimited.
INSERT INTO `plans` (`name`, `slug`, `description`, `price_monthly`, `price_yearly`, `currency`, `trial_days`, `billing_type`, `is_active`, `is_featured`, `sort_order`, `created_at`, `updated_at`) VALUES
('Free Trial', 'trial', 'Try every feature free', 0, 0, 'INR', 7, 'subscription', 1, 0, 0, NOW(), NOW()),
('Starter', 'starter', 'For small businesses starting on WhatsApp', 999, 9990, 'INR', 7, 'subscription', 1, 0, 1, NOW(), NOW()),
('Growth', 'growth', 'For growing teams with automation and AI', 2499, 24990, 'INR', 7, 'subscription', 1, 1, 2, NOW(), NOW()),
('Pro', 'pro', 'Unlimited power for serious businesses', 4999, 49990, 'INR', 7, 'subscription', 1, 0, 3, NOW(), NOW());

INSERT INTO `plan_features` (`plan_id`, `feature`, `value`)
SELECT p.id, f.feature, f.value FROM `plans` p
JOIN (
  SELECT 'trial' AS slug, 'contacts' AS feature, '500' AS value
  UNION ALL SELECT 'trial', 'messages_monthly', '1000'
  UNION ALL SELECT 'trial', 'agents', '2'
  UNION ALL SELECT 'trial', 'waba_numbers', '1'
  UNION ALL SELECT 'trial', 'campaigns_monthly', '5'
  UNION ALL SELECT 'trial', 'ai_tokens_monthly', '100000'
  UNION ALL SELECT 'trial', 'storage_mb', '250'
  UNION ALL SELECT 'trial', 'api_calls_daily', '1000'
  UNION ALL SELECT 'trial', 'bot_flows', '2'
  UNION ALL SELECT 'starter', 'contacts', '5000'
  UNION ALL SELECT 'starter', 'messages_monthly', '10000'
  UNION ALL SELECT 'starter', 'agents', '3'
  UNION ALL SELECT 'starter', 'waba_numbers', '1'
  UNION ALL SELECT 'starter', 'campaigns_monthly', '20'
  UNION ALL SELECT 'starter', 'ai_tokens_monthly', '500000'
  UNION ALL SELECT 'starter', 'storage_mb', '1024'
  UNION ALL SELECT 'starter', 'api_calls_daily', '10000'
  UNION ALL SELECT 'starter', 'bot_flows', '5'
  UNION ALL SELECT 'growth', 'contacts', '25000'
  UNION ALL SELECT 'growth', 'messages_monthly', '50000'
  UNION ALL SELECT 'growth', 'agents', '10'
  UNION ALL SELECT 'growth', 'waba_numbers', '2'
  UNION ALL SELECT 'growth', 'campaigns_monthly', '100'
  UNION ALL SELECT 'growth', 'ai_tokens_monthly', '2000000'
  UNION ALL SELECT 'growth', 'storage_mb', '5120'
  UNION ALL SELECT 'growth', 'api_calls_daily', '50000'
  UNION ALL SELECT 'growth', 'bot_flows', '20'
  UNION ALL SELECT 'pro', 'contacts', '-1'
  UNION ALL SELECT 'pro', 'messages_monthly', '-1'
  UNION ALL SELECT 'pro', 'agents', '-1'
  UNION ALL SELECT 'pro', 'waba_numbers', '5'
  UNION ALL SELECT 'pro', 'campaigns_monthly', '-1'
  UNION ALL SELECT 'pro', 'ai_tokens_monthly', '10000000'
  UNION ALL SELECT 'pro', 'storage_mb', '20480'
  UNION ALL SELECT 'pro', 'api_calls_daily', '-1'
  UNION ALL SELECT 'pro', 'bot_flows', '-1'
) f ON f.slug = p.slug;

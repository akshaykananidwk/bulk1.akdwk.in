ALTER TABLE `waba_accounts`
  MODIFY `token_mode` ENUM('embedded_signup','coexistence','permanent_system_user','temporary_manual') NOT NULL DEFAULT 'embedded_signup';

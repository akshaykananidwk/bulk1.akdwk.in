-- Default roles (tenant_id NULL = system template roles cloned per tenant)
INSERT INTO `roles` (`tenant_id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
(NULL, 'Owner', 'owner', 'Workspace owner with full access', 1, NOW(), NOW()),
(NULL, 'Admin', 'admin', 'Manage everything except billing and workspace deletion', 1, NOW(), NOW()),
(NULL, 'Manager', 'manager', 'Manage inbox, campaigns, contacts and reports', 1, NOW(), NOW()),
(NULL, 'Agent', 'agent', 'Handle assigned conversations in the team inbox', 1, NOW(), NOW()),
(NULL, 'Viewer', 'viewer', 'Read-only access to reports and conversations', 1, NOW(), NOW());

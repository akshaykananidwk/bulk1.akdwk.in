-- Permission matrix. Grouped by module; slugs follow "module.action".
INSERT INTO `permissions` (`slug`, `group_name`, `description`) VALUES
-- Dashboard
('dashboard.view', 'dashboard', 'View dashboard'),
-- Inbox
('inbox.view', 'inbox', 'View team inbox'),
('inbox.view_all', 'inbox', 'View all conversations (not just assigned)'),
('inbox.send', 'inbox', 'Send messages'),
('inbox.assign', 'inbox', 'Assign / transfer conversations'),
('inbox.notes', 'inbox', 'Add private notes'),
('inbox.delete', 'inbox', 'Delete messages'),
('inbox.export', 'inbox', 'Export conversations'),
-- Contacts
('contacts.view', 'contacts', 'View contacts'),
('contacts.create', 'contacts', 'Create contacts'),
('contacts.edit', 'contacts', 'Edit contacts'),
('contacts.delete', 'contacts', 'Delete contacts'),
('contacts.import', 'contacts', 'Import contacts'),
('contacts.export', 'contacts', 'Export contacts'),
('contacts.fields', 'contacts', 'Manage custom fields'),
-- Groups & segments
('groups.manage', 'contacts', 'Manage groups'),
('segments.manage', 'contacts', 'Manage segments'),
('tags.manage', 'contacts', 'Manage tags'),
-- Templates
('templates.view', 'templates', 'View templates'),
('templates.create', 'templates', 'Create / submit templates'),
('templates.edit', 'templates', 'Edit templates'),
('templates.delete', 'templates', 'Delete templates'),
('templates.sync', 'templates', 'Sync templates from Meta'),
-- Campaigns
('campaigns.view', 'campaigns', 'View campaigns'),
('campaigns.create', 'campaigns', 'Create campaigns'),
('campaigns.edit', 'campaigns', 'Edit campaigns'),
('campaigns.delete', 'campaigns', 'Delete campaigns'),
('campaigns.send', 'campaigns', 'Launch campaigns'),
('campaigns.control', 'campaigns', 'Pause / resume / cancel campaigns'),
-- Flows / bot
('flows.view', 'flows', 'View bot flows'),
('flows.create', 'flows', 'Create flows'),
('flows.edit', 'flows', 'Edit flows'),
('flows.delete', 'flows', 'Delete flows'),
('flows.publish', 'flows', 'Activate / deactivate flows'),
-- AI
('ai.view', 'ai', 'View AI agents'),
('ai.manage', 'ai', 'Manage AI agents and providers'),
('ai.train', 'ai', 'Train knowledge bases'),
('ai.usage', 'ai', 'View AI usage and costs'),
-- WhatsApp settings
('whatsapp.view', 'whatsapp', 'View WhatsApp accounts'),
('whatsapp.connect', 'whatsapp', 'Connect / disconnect WABA'),
('whatsapp.numbers', 'whatsapp', 'Manage phone numbers'),
-- Marketing
('marketing.landing_pages', 'marketing', 'Manage landing pages'),
('marketing.forms', 'marketing', 'Manage forms'),
('marketing.qr', 'marketing', 'Manage QR codes'),
('marketing.popups', 'marketing', 'Manage popups'),
-- E-commerce
('ecommerce.view', 'ecommerce', 'View stores and orders'),
('ecommerce.manage', 'ecommerce', 'Manage store integrations'),
-- Team
('team.view', 'team', 'View team members'),
('team.manage', 'team', 'Invite / edit / remove team members'),
('team.roles', 'team', 'Manage roles and permissions'),
('team.departments', 'team', 'Manage departments'),
-- Automation settings
('automation.rules', 'automation', 'Manage assignment rules and SLA policies'),
('automation.quick_replies', 'automation', 'Manage quick replies'),
-- Billing
('billing.view', 'billing', 'View invoices and subscription'),
('billing.manage', 'billing', 'Change plan, pay invoices, manage wallet'),
-- Integrations & API
('integrations.view', 'integrations', 'View integrations'),
('integrations.manage', 'integrations', 'Manage integrations'),
('api.keys', 'integrations', 'Manage API keys'),
('api.webhooks', 'integrations', 'Manage outbound webhooks'),
-- Reports
('reports.view', 'reports', 'View reports'),
('reports.export', 'reports', 'Export reports'),
-- Settings
('settings.view', 'settings', 'View workspace settings'),
('settings.manage', 'settings', 'Manage workspace settings'),
('settings.white_label', 'settings', 'Manage white-label branding'),
-- Super admin only
('admin.tenants', 'admin', 'Manage tenants'),
('admin.plans', 'admin', 'Manage plans'),
('admin.billing', 'admin', 'Manage platform billing'),
('admin.settings', 'admin', 'Manage global settings'),
('admin.logs', 'admin', 'View system logs'),
('admin.queue', 'admin', 'Manage the job queue'),
('admin.tickets', 'admin', 'Manage support tickets'),
('admin.announcements', 'admin', 'Manage announcements'),
('update.manage', 'admin', 'Run system updates'),
('backup.manage', 'admin', 'Manage backups');

-- Role grants: Owner/Admin get everything tenant-side; Manager broad; Agent inbox-centric; Viewer read-only.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'owner' AND r.tenant_id IS NULL AND p.group_name NOT IN ('admin');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'admin' AND r.tenant_id IS NULL AND p.group_name NOT IN ('admin') AND p.slug NOT IN ('billing.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'manager' AND r.tenant_id IS NULL AND p.slug IN
('dashboard.view','inbox.view','inbox.view_all','inbox.send','inbox.assign','inbox.notes','inbox.export',
 'contacts.view','contacts.create','contacts.edit','contacts.import','contacts.export','contacts.fields',
 'groups.manage','segments.manage','tags.manage',
 'templates.view','templates.create','templates.edit','templates.sync',
 'campaigns.view','campaigns.create','campaigns.edit','campaigns.send','campaigns.control',
 'flows.view','flows.create','flows.edit','flows.publish',
 'ai.view','ai.train','ai.usage',
 'marketing.landing_pages','marketing.forms','marketing.qr','marketing.popups',
 'ecommerce.view','automation.rules','automation.quick_replies',
 'reports.view','reports.export','team.view');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'agent' AND r.tenant_id IS NULL AND p.slug IN
('dashboard.view','inbox.view','inbox.send','inbox.notes',
 'contacts.view','contacts.create','contacts.edit','templates.view','automation.quick_replies');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'viewer' AND r.tenant_id IS NULL AND p.slug IN
('dashboard.view','inbox.view','contacts.view','templates.view','campaigns.view','flows.view','reports.view');

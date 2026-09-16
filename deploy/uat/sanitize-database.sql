-- Run only with the crm_uat database account against the UAT DB container.
-- Column names checked against the production snapshot on 2026-09-11.
START TRANSACTION;
DELETE FROM auth_token;
DELETE FROM job;
DELETE FROM email_queue_item;
DELETE FROM webhook_queue_item;
DELETE FROM webhook_event_queue_item;
UPDATE scheduled_job SET status = 'Inactive';
UPDATE webhook SET is_active = 0, secret_key = NULL;
UPDATE integration SET enabled = 0, data = NULL;
UPDATE email_account SET status = 'Inactive', use_imap = 0, use_smtp = 0,
    password = NULL, smtp_password = NULL;
UPDATE inbound_email SET status = 'Inactive', use_imap = 0, use_smtp = 0,
    password = NULL, smtp_password = NULL, reply = 0;
UPDATE o_auth_account SET access_token = NULL, refresh_token = NULL;
UPDATE o_auth_provider SET is_active = 0, client_secret = NULL;
UPDATE authentication_provider SET deleted = 1, oidc_client_secret = NULL;
-- Preserve record ownership/history, but do not reuse production login access.
UPDATE user SET is_active = 0, password = NULL, api_key = NULL;
COMMIT;

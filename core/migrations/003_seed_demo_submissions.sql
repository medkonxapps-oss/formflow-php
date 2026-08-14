-- Seed sample form + submissions for inbox UI testing (dev/demo).
-- Safe to re-run: only inserts when no forms exist for user 1.

INSERT INTO forms (user_id, name, slug, status, fields_json, settings_json, created_at, updated_at)
SELECT 1, 'Contact Us', 'contact-us', 'active',
    '[{"id":"f_name","type":"text","label":"Full Name","placeholder":"Jane Doe","required":true,"validation":{"min_length":2,"max_length":100,"regex":"","error_message":""},"default":"","options":[]},{"id":"f_email","type":"email","label":"Email","placeholder":"you@example.com","required":true,"validation":{"min_length":0,"max_length":255,"regex":"","error_message":""},"default":"","options":[]},{"id":"f_message","type":"textarea","label":"Message","placeholder":"How can we help?","required":true,"validation":{"min_length":10,"max_length":5000,"regex":"","error_message":""},"default":"","options":[]}]',
    '{"success":{"type":"message","message":"Thank you! We will be in touch soon.","redirect_url":""},"notifications":{"recipients":["admin@formflow.local"],"subject":"New contact submission","auto_reply":false},"spam":{"honeypot":true,"recaptcha":false,"recaptcha_site_key":"","recaptcha_secret_key":""},"allowed_domains":[],"webhook_url":""}',
    UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM DUAL
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1)
  AND NOT EXISTS (SELECT 1 FROM forms WHERE slug = 'contact-us');

INSERT INTO submissions (form_id, data_json, ip_address, user_agent, referrer, is_spam, is_read, is_starred, created_at)
SELECT f.id,
    '{"f_name":"Alice Johnson","f_email":"alice@example.com","f_message":"I would like to learn more about your services."}',
    '192.168.1.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'https://example.com/contact', 0, 1, 1, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)
FROM forms f WHERE f.slug = 'contact-us'
  AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.form_id = f.id AND s.data_json LIKE '%alice@example.com%');

INSERT INTO submissions (form_id, data_json, ip_address, user_agent, referrer, is_spam, is_read, is_starred, created_at)
SELECT f.id,
    '{"f_name":"Bob Smith","f_email":"bob@example.com","f_message":"Please call me back regarding pricing."}',
    '10.0.0.55', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', 'https://example.com/', 0, 0, 0, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
FROM forms f WHERE f.slug = 'contact-us'
  AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.form_id = f.id AND s.data_json LIKE '%bob@example.com%');

INSERT INTO submissions (form_id, data_json, ip_address, user_agent, referrer, is_spam, is_read, is_starred, created_at)
SELECT f.id,
    '{"f_name":"Spam Bot","f_email":"spam@bad.net","f_message":"Buy cheap pills now!!!"}',
    '203.0.113.99', 'curl/7.68.0', '', 1, 0, 0, UTC_TIMESTAMP()
FROM forms f WHERE f.slug = 'contact-us'
  AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.form_id = f.id AND s.data_json LIKE '%spam@bad.net%');

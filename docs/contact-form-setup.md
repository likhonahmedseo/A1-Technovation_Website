# Contact Form Setup

This site now uses a first-party PHP endpoint for the contact form instead of Formspree. The handler can:

- validate submissions
- require a server-backed math CAPTCHA and keep the honeypot spam trap
- save leads to MySQL
- send notification emails over SMTP
- send an automatic thank-you reply to the visitor

## 1. Requirements

- PHP 8.1 or newer
- MySQL or MariaDB
- SMTP credentials for the mailbox you want to send from

## 2. Create the Contact Database Table

Use the schema in [database/contact_submissions.sql](/d:/A1 Technovation/A1 Technovation - Website/database/contact_submissions.sql).

Example with MySQL CLI:

```bash
mysql -u your_user -p your_database < database/contact_submissions.sql
```

If the table already exists, add the IP/country columns with:

```sql
ALTER TABLE contact_submissions
  ADD COLUMN remote_address VARCHAR(45) DEFAULT NULL AFTER ip_address,
  ADD COLUMN forwarded_for VARCHAR(255) DEFAULT NULL AFTER remote_address,
  ADD COLUMN country_code CHAR(2) DEFAULT NULL AFTER forwarded_for,
  ADD COLUMN country_name VARCHAR(80) DEFAULT NULL AFTER country_code,
  ADD KEY idx_contact_submissions_ip_address (ip_address),
  ADD KEY idx_contact_submissions_country_code (country_code);
```

## 3. Configure the App

1. Copy [config/contact-form.example.php](/d:/A1 Technovation/A1 Technovation - Website/config/contact-form.example.php) to `config/contact-form.php`.
2. Fill in:
   - MySQL host, database, username, and password
   - SMTP host, port, username, password, and encryption
   - sender and notification email addresses
   - optional `captcha.ttl_seconds`, `captcha.min_number`, and `captcha.max_number` values
   - optional `mail.transport` and `mail.fallback_to_native_mail` settings
3. Set `database.enabled` to `true` when you want submissions stored in MySQL.

Important:

- `config/contact-form.php` is gitignored so secrets do not get committed.
- `app.contact_page` should stay `/pages/contact.html` unless you move the contact page.

## 4. SMTP Notes

Recommended settings depend on your provider:

- Gmail / Google Workspace: host `smtp.gmail.com`, port `587`, encryption `tls`
- cPanel hosting mail: usually host `mail.a1technovation.com`, port `465` with `ssl` or port `587` with `tls`
- Microsoft 365: host `smtp.office365.com`, port `587`, encryption `tls`

If your provider requires an app password, use that instead of your normal mailbox password.

For this website on cPanel, the most production-friendly setup is usually:

- SMTP host: `mail.a1technovation.com`
- SMTP port: `465`
- Encryption: `ssl`
- SMTP username: the full mailbox address, for example `info@a1technovation.com`
- SMTP password: the mailbox password created in cPanel
- `mail.transport`: `smtp`
- `mail.fallback_to_native_mail`: `true`
- `smtp.helo_host`: `a1technovation.com`

That setup avoids depending on Formspree and sends directly from your own hosting mailbox.

You can also keep `mail.transport` as `smtp` and leave `mail.fallback_to_native_mail` set to `true`. In that mode, the handler will try SMTP first and then fall back to PHP's built-in `mail()` if SMTP is blocked by the host.

## 5. Deploy

Upload the full site, including:

- `php/`
- `config/contact-form.php`
- `database/` if you want to keep the schema file on the server

Make sure your hosting serves PHP files and allows outbound SMTP connections.

## 6. Test the Form

1. Open the contact page.
2. Submit a test lead.
3. Confirm:
   - the record appears in `contact_submissions`
   - the notification email includes IP address and country details
   - the notification email arrives in the admin inbox
   - the visitor receives the thank-you email
   - the math CAPTCHA loads and rejects a wrong answer
   - the submission is sent through `/php/contact-submit.php`, not Formspree

## 7. Files Involved

- Frontend page: [pages/contact.html](/d:/A1 Technovation/A1 Technovation - Website/pages/contact.html)
- Frontend script: [js/main.js](/d:/A1 Technovation/A1 Technovation - Website/js/main.js)
- SMTP mailer: [php/SmtpMailer.php](/d:/A1 Technovation/A1 Technovation - Website/php/SmtpMailer.php)
- CAPTCHA endpoint: [php/contact-captcha.php](/d:/A1 Technovation/A1 Technovation - Website/php/contact-captcha.php)
- PHP bootstrap: [php/bootstrap.php](/d:/A1 Technovation/A1 Technovation - Website/php/bootstrap.php)
- PHP form handler: [php/contact-submit.php](/d:/A1 Technovation/A1 Technovation - Website/php/contact-submit.php)

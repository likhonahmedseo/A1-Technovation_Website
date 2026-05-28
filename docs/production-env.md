# Production Environment Variables

Set these on the hosting server before using the CMS or contact form.

## CMS

- `CMS_BASE_URL=https://a1technovation.com`
- `CMS_DB_HOST=localhost`
- `CMS_DB_PORT=3306`
- `CMS_DB_NAME=...`
- `CMS_DB_USERNAME=...`
- `CMS_DB_PASSWORD=...`
- `CMS_SMTP_HOST=...`
- `CMS_SMTP_PORT=587`
- `CMS_SMTP_USERNAME=...`
- `CMS_SMTP_PASSWORD=...`
- `CMS_MAIL_FROM_EMAIL=...`
- `PEXELS_API_KEY=...` optional
- `PIXABAY_API_KEY=...` optional
- `UNSPLASH_ACCESS_KEY=...` optional

Stock image keys can also be replaced from `Settings > Stock image APIs` in the CMS dashboard.

## Contact Form

- `CONTACT_BASE_URL=https://a1technovation.com`
- `CONTACT_DB_ENABLED=true`
- `CONTACT_DB_HOST=localhost`
- `CONTACT_DB_PORT=3306`
- `CONTACT_DB_NAME=...`
- `CONTACT_DB_USERNAME=...`
- `CONTACT_DB_PASSWORD=...`
- `CONTACT_SMTP_HOST=...`
- `CONTACT_SMTP_PORT=587`
- `CONTACT_SMTP_USERNAME=...`
- `CONTACT_SMTP_PASSWORD=...`
- `CONTACT_MAIL_TRANSPORT=smtp` or `native`
- `CONTACT_NOTIFICATION_EMAIL=...`

Do not commit real database, SMTP, or API credentials into `config/*.php`.

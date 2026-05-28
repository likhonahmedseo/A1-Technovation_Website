# A1 Technovation Website

A production-ready marketing website for A1 Technovation built with static HTML, shared CSS, vanilla JavaScript, and a PHP contact-form backend for SMTP email delivery and lead capture.

## Project Structure

```text
A1 Technovation - Website/
|-- index.html
|-- assets/
|   `-- images/
|-- css/
|   |-- responsive.css
|   `-- style.css
|-- database/
|   `-- contact_submissions.sql
|-- docs/
|   `-- contact-form-setup.md
|-- js/
|   `-- main.js
|-- pages/
|   |-- about.html
|   |-- blog.html
|   |-- contact.html
|   |-- portfolio.html
|   |-- privacy.html
|   |-- services-ppc-ads.html
|   |-- services-seo.html
|   |-- services-social-media.html
|   |-- services-web-dev.html
|   |-- services.html
|   `-- terms.html
|-- php/
|   |-- bootstrap.php
|   |-- SmtpMailer.php
|   `-- contact-submit.php
|-- config/
|   `-- contact-form.example.php
`-- README.md
```

## Features

- Responsive marketing site with shared navigation and mobile menu
- SEO and social preview metadata across all public pages
- Brand logo system with light and dark logo variants
- Contact form validation on the frontend
- Built-in math CAPTCHA plus honeypot protection for spam reduction
- PHP contact form endpoint with built-in SMTP delivery
- Optional MySQL storage for contact submissions
- Deployment guide and database schema for production setup

## Contact Form Stack

- Frontend form: [pages/contact.html](/d:/A1 Technovation/A1 Technovation - Website/pages/contact.html)
- Frontend behavior: [js/main.js](/d:/A1 Technovation/A1 Technovation - Website/js/main.js)
- CAPTCHA endpoint: [php/contact-captcha.php](/d:/A1 Technovation/A1 Technovation - Website/php/contact-captcha.php)
- PHP handler: [php/contact-submit.php](/d:/A1 Technovation/A1 Technovation - Website/php/contact-submit.php)
- Config template: [config/contact-form.example.php](/d:/A1 Technovation/A1 Technovation - Website/config/contact-form.example.php)
- Database schema: [database/contact_submissions.sql](/d:/A1 Technovation/A1 Technovation - Website/database/contact_submissions.sql)
- Setup guide: [docs/contact-form-setup.md](/d:/A1 Technovation/A1 Technovation - Website/docs/contact-form-setup.md)

## Requirements

- PHP 8.1+
- MySQL 8+ or MariaDB 10.5+ if you want lead storage
- SMTP credentials from your email provider

## Quick Start

1. Copy `config/contact-form.example.php` to `config/contact-form.php`.
2. Add your SMTP credentials in `config/contact-form.php`.
3. Import `database/contact_submissions.sql` into MySQL if you want to save leads.
4. Deploy the site to hosting with PHP enabled.

## Notes

- `config/contact-form.php` is ignored by git so production credentials stay local.
- The site remains static everywhere except the contact form endpoint.
- No Composer or third-party PHP package install is required.

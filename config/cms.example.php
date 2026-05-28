<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => getenv('CMS_APP_NAME') ?: 'A1 Technovation CMS',
        'base_url' => getenv('CMS_BASE_URL') ?: 'https://a1technovation.com',
        'timezone' => getenv('CMS_TIMEZONE') ?: 'Asia/Dhaka',
        'session_name' => getenv('CMS_SESSION_NAME') ?: 'a1tech_cms',
        'default_author' => getenv('CMS_DEFAULT_AUTHOR') ?: 'A1 Technovation',
        'media_path' => getenv('CMS_MEDIA_PATH') ?: 'assets/uploads/media',
        'ckeditor_cdn' => getenv('CMS_CKEDITOR_CDN') ?: 'https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js',
    ],
    'database' => [
        'host' => getenv('CMS_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('CMS_DB_PORT') ?: 3306),
        'name' => getenv('CMS_DB_NAME') ?: '',
        'username' => getenv('CMS_DB_USERNAME') ?: '',
        'password' => getenv('CMS_DB_PASSWORD') ?: '',
        'charset' => getenv('CMS_DB_CHARSET') ?: 'utf8mb4',
    ],
    'smtp' => [
        'host'       => getenv('CMS_SMTP_HOST') ?: '',
        'port'       => (int) (getenv('CMS_SMTP_PORT') ?: 587),
        'username'   => getenv('CMS_SMTP_USERNAME') ?: '',
        'password'   => getenv('CMS_SMTP_PASSWORD') ?: '',
        'encryption' => getenv('CMS_SMTP_ENCRYPTION') ?: 'tls',
        'auth'       => (getenv('CMS_SMTP_AUTH') ?: 'true') !== 'false',
        'timeout'    => (int) (getenv('CMS_SMTP_TIMEOUT') ?: 20),
        'helo_host'  => getenv('CMS_SMTP_HELO_HOST') ?: 'yourdomain.com',
    ],
    'mail' => [
        'from_email' => getenv('CMS_MAIL_FROM_EMAIL') ?: '',
        'from_name'  => getenv('CMS_MAIL_FROM_NAME') ?: 'Your CMS Name',
    ],
    'stock_images' => [
        'pexels_api_key' => getenv('PEXELS_API_KEY') ?: '',
        'pixabay_api_key' => getenv('PIXABAY_API_KEY') ?: '',
        'unsplash_access_key' => getenv('UNSPLASH_ACCESS_KEY') ?: '',
    ],
];

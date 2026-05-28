<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';

$language = strtolower(trim((string) ($_GET['lang'] ?? '')));
$requestPath = trim((string) ($_GET['path'] ?? ''), "/ \t\n\r\0\x0B");

$languageConfig = [
    'ar' => ['html' => 'ar', 'dir' => 'rtl', 'name' => 'Arabic', 'locale' => 'ar_AR'],
    'zh' => ['html' => 'zh-CN', 'dir' => 'ltr', 'name' => 'Chinese', 'locale' => 'zh_CN'],
    'es' => ['html' => 'es', 'dir' => 'ltr', 'name' => 'Spanish', 'locale' => 'es_ES'],
    'de' => ['html' => 'de', 'dir' => 'ltr', 'name' => 'Deutsch', 'locale' => 'de_DE'],
    'sv' => ['html' => 'sv', 'dir' => 'ltr', 'name' => 'Svenska', 'locale' => 'sv_SE'],
];

if (!isset($languageConfig[$language])) {
    http_response_code(404);
    exit('Language not found.');
}

function a1_localized_static_path(string $requestPath): ?string
{
    $requestPath = trim(rawurldecode($requestPath), '/');
    $requestPath = preg_replace('/\.html$/i', '', $requestPath) ?? $requestPath;

    if ($requestPath === '') {
        return 'index.html';
    }

    $aliases = [
        'blog' => 'pages/blog.html',
        'aeo-optimization-services' => 'pages/services-aeo-optimization.html',
        'geo-optimization-services' => 'pages/services-geo-optimization.html',
        'ecommerce-seo-services' => 'pages/ecommerce-seo-services.html',
        'car-dealership-seo-service-bangladesh' => 'pages/car-dealership-seo-service-bangladesh.html',
        'best-seo-expert-in-bangladesh' => 'pages/best-seo-expert-in-bangladesh.html',
    ];

    if (isset($aliases[$requestPath]) && is_file(cms_root($aliases[$requestPath]))) {
        return $aliases[$requestPath];
    }

    $candidates = [
        $requestPath . '.html',
        'pages/' . basename($requestPath) . '.html',
    ];

    foreach ($candidates as $candidate) {
        if (is_file(cms_root($candidate))) {
            return $candidate;
        }
    }

    return null;
}

function a1_localized_url(string $language, string $englishUrl): string
{
    $path = (string) parse_url($englishUrl, PHP_URL_PATH);
    $path = $path === '' ? '/' : $path;
    return cms_url($language . ($path === '/' ? '/' : $path));
}

function a1_replace_or_insert_head(string $html, string $pattern, string $replacement): string
{
    if (preg_match($pattern, $html) === 1) {
        return (string) preg_replace_callback($pattern, static fn (): string => $replacement, $html, 1);
    }

    return (string) preg_replace_callback('#</head>#i', static fn (): string => '  ' . $replacement . "\n</head>", $html, 1);
}

function a1_localized_phrase_map(string $language): array
{
    $maps = [
        'es' => [
            'Home' => 'Inicio',
            'About' => 'Nosotros',
            'Services' => 'Servicios',
            'Work' => 'Proyectos',
            'Blog' => 'Blog',
            'Tools' => 'Herramientas',
            'Contact' => 'Contacto',
            'Free Growth Audit' => 'Auditoria gratuita',
            'Free Consultation' => 'Consulta gratuita',
            'SEO Services' => 'Servicios SEO',
            'Local SEO' => 'SEO local',
            'Web Design' => 'Diseno web',
            'PPC Advertising' => 'Publicidad PPC',
            'Social Media' => 'Redes sociales',
            'Privacy Policy' => 'Politica de privacidad',
            'Terms and Conditions' => 'Terminos y condiciones',
            'About Us' => 'Nosotros',
            'Our Work' => 'Nuestro trabajo',
            'Get Free Audit' => 'Solicitar auditoria gratis',
            'Get Free Growth Audit' => 'Solicitar auditoria gratis',
        ],
        'zh' => [
            'Home' => '首页',
            'About' => '关于我们',
            'Services' => '服务',
            'Work' => '案例',
            'Blog' => '博客',
            'Tools' => '工具',
            'Contact' => '联系',
            'Free Growth Audit' => '免费增长审计',
            'Free Consultation' => '免费咨询',
            'SEO Services' => 'SEO 服务',
            'Local SEO' => '本地 SEO',
            'Web Design' => '网站设计',
            'PPC Advertising' => 'PPC 广告',
            'Social Media' => '社交媒体',
            'Privacy Policy' => '隐私政策',
            'Terms and Conditions' => '条款与条件',
            'About Us' => '关于我们',
            'Our Work' => '我们的案例',
            'Get Free Audit' => '获取免费审计',
            'Get Free Growth Audit' => '获取免费增长审计',
        ],
        'ar' => [
            'Home' => 'الرئيسية',
            'About' => 'من نحن',
            'Services' => 'الخدمات',
            'Work' => 'الأعمال',
            'Blog' => 'المدونة',
            'Tools' => 'الأدوات',
            'Contact' => 'اتصل بنا',
            'Free Growth Audit' => 'تدقيق نمو مجاني',
            'Free Consultation' => 'استشارة مجانية',
            'SEO Services' => 'خدمات تحسين محركات البحث',
            'Local SEO' => 'تحسين البحث المحلي',
            'Web Design' => 'تصميم المواقع',
            'PPC Advertising' => 'إعلانات الدفع بالنقرة',
            'Social Media' => 'وسائل التواصل الاجتماعي',
            'Privacy Policy' => 'سياسة الخصوصية',
            'Terms and Conditions' => 'الشروط والأحكام',
            'About Us' => 'من نحن',
            'Our Work' => 'أعمالنا',
            'Get Free Audit' => 'احصل على تدقيق مجاني',
            'Get Free Growth Audit' => 'احصل على تدقيق نمو مجاني',
        ],
    ];

    return $maps[$language] ?? [];
}

$staticPath = a1_localized_static_path($requestPath);
if ($staticPath === null && preg_match('#^(blog|page|services|landing)/([^/]+)$#', trim($requestPath, '/'), $dynamicMatch) === 1) {
    $_GET['slug'] = $dynamicMatch[2];
    $_SERVER['REQUEST_URI'] = '/' . trim($requestPath, '/');
    ob_start();
    require ($dynamicMatch[1] === 'blog' ? __DIR__ . '/blog-post.php' : __DIR__ . '/public-page.php');
    $html = (string) ob_get_clean();
    $englishPath = trim($requestPath, '/');
    $staticPath = '';
} elseif ($staticPath === null) {
    http_response_code(404);
    $staticPath = '404.html';
    $html = (string) file_get_contents(cms_root($staticPath));
    $englishPath = '404';
} else {
    $html = (string) file_get_contents(cms_root($staticPath));
    $englishPath = $staticPath === 'index.html' ? '' : preg_replace('/\.html$/', '', $staticPath);
}

$englishUrl = cms_url((string) $englishPath);
$localizedUrl = a1_localized_url($language, $englishUrl);
$config = $languageConfig[$language];

$title = cms_static_page_title($html, $staticPath);
$description = cms_static_page_meta_value($html, 'description', '');
$localizedDescription = match ($language) {
    'ar' => 'نسخة متعددة اللغات من صفحة A1 Technovation. استخدم محدد اللغة للوصول إلى المحتوى والخدمات المناسبة.',
    'zh' => 'A1 Technovation 页面的多语言版本。使用语言选择器访问相关服务和内容。',
    'es' => 'Version multilingue de A1 Technovation. Usa el selector de idioma para consultar servicios y contenido.',
    default => $description,
};

$html = preg_replace('#<html([^>]*)>#i', '<html lang="' . cms_e((string) $config['html']) . '" dir="' . cms_e((string) $config['dir']) . '">', $html, 1) ?? $html;
$html = a1_replace_or_insert_head($html, '#<base[^>]*>#i', '<base href="' . cms_e(cms_url('')) . '/" />');
$html = a1_replace_or_insert_head($html, '#<meta[^>]+name=["\']robots["\'][^>]*>#i', '<meta name="robots" content="noindex,follow" />');
$html = a1_replace_or_insert_head($html, '#<meta[^>]+name=["\']description["\'][^>]*>#i', '<meta name="description" content="' . cms_e($localizedDescription) . '" />');
$html = a1_replace_or_insert_head($html, '#<meta[^>]+property=["\']og:locale["\'][^>]*>#i', '<meta property="og:locale" content="' . cms_e((string) $config['locale']) . '" />');
$html = a1_replace_or_insert_head($html, '#<meta[^>]+property=["\']og:url["\'][^>]*>#i', '<meta property="og:url" content="' . cms_e($localizedUrl) . '" />');
$html = a1_replace_or_insert_head($html, '#<link[^>]+rel=["\']canonical["\'][^>]*>#i', '<link rel="canonical" href="' . cms_e($englishUrl) . '" />');
$html = a1_replace_or_insert_head($html, '#<title[^>]*>.*?</title>#is', '<title>' . cms_e($title . ' | ' . $config['name']) . '</title>');

foreach (a1_localized_phrase_map($language) as $english => $translated) {
    $html = str_replace('>' . $english . '<', '>' . $translated . '<', $html);
    $html = str_replace(' ' . $english . ' ', ' ' . $translated . ' ', $html);
}

if (!str_contains($html, 'language-switcher')) {
    $script = '<script src="' . cms_e(cms_url('js/language-switcher.min.js?v=20260514')) . '"></script>';
    $html = preg_replace('#</body>#i', $script . "\n</body>", $html, 1) ?? ($html . $script);
}

echo $html;

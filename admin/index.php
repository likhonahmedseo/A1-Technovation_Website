<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

if (cms_setup_required()) {
    cms_redirect('admin/setup.php');
}

if (cms_is_logged_in()) {
    cms_redirect('admin/dashboard.php');
}

cms_redirect('admin/login.php');

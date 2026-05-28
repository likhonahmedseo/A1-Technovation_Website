<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_logout();
cms_flash_set('success', 'You have been signed out.');
cms_redirect('admin/login.php');

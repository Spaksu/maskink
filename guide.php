<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/partials/layout.php';
require __DIR__ . '/partials/guide.php';

mk_boot_or_die();
mk_session();

$maxKb = (int)Settings::get('max_input_kb', '4096');
$needLogin = (int)Settings::get('mask_requires_login', '0') === 1;

mk_head(t('User Guide'), 'guide');

// The guide text is a separate file per language (guides/user.<lang>.php)
require __DIR__ . '/guides/user.' . (I18n::lang() === 'tr' ? 'tr' : 'en') . '.php';

mk_foot();

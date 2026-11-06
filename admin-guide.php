<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/partials/layout.php';
require __DIR__ . '/partials/guide.php';

mk_boot_or_die();
mk_session();

// The administrator guide is only available to logged-in administrators.
if (!Auth::check()) {
    header('Location: settings.php');
    exit;
}

mk_head(t('Administrator Guide'), 'admin-guide');

// The guide text is a separate file per language (guides/admin.<lang>.php)
require __DIR__ . '/guides/admin.' . (I18n::lang() === 'tr' ? 'tr' : 'en') . '.php';

mk_foot();

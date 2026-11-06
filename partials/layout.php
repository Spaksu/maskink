<?php
declare(strict_types=1);

function mk_logo(string $cls = 'h-8 w-8'): string
{
    return '<img src="assets/img/logo-icon.png" class="' . h($cls) . ' object-contain" alt="" aria-hidden="true">';
}

/** The full logo for roomy places; small places use the icon from mk_logo() */
function mk_logo_full(string $cls = 'h-24 w-auto'): string
{
    return '<img src="assets/img/logo.png" class="' . h($cls) . '" alt="' . h(MK_APP) . '">';
}

/** Language switcher: shows the other language and switches to it */
function mk_lang_switch(): string
{
    $cur = I18n::lang();
    $other = $cur === 'en' ? 'tr' : 'en';
    return '<button type="button" id="langToggle" data-lang="' . $other . '" title="' . h(I18n::LANGS[$other]) . '" aria-label="' . h(I18n::LANGS[$other]) . '"'
        . ' class="rounded-md px-2 py-1.5 text-xs font-semibold tracking-wide text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">'
        . strtoupper($other) . '</button>';
}

function mk_head(string $title, string $active, array $extra = []): void
{
    $csrf = Auth::csrf();
    $i18n = ['lang' => I18n::lang(), 'locale' => I18n::locale(), 'dict' => (object)I18n::dict()];
    ?>
<!doctype html>
<html lang="<?= h(I18n::lang()) ?>" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h($csrf) ?>">
<title><?= h($title) ?> · <?= MK_APP ?></title>
<link rel="icon" type="image/png" href="assets/img/favicon.png">
<script>
  (function () {
    try {
      var t = localStorage.getItem('mki-theme');
      if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
    } catch (e) {}
  })();
  window.MK_I18N = <?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="assets/js/tailwind.js"></script>
<script>
  tailwind.config = {
    darkMode: 'class',
    theme: { extend: {
      fontFamily: {
        sans: ['Inter', 'Segoe UI', 'system-ui', '-apple-system', 'Roboto', 'sans-serif'],
        mono: ['Cascadia Code', 'JetBrains Mono', 'Consolas', 'ui-monospace', 'SFMono-Regular', 'monospace']
      }
    } }
  };
</script>
<link rel="stylesheet" href="assets/css/app.css?v=<?= (int)@filemtime(MK_ROOT . '/assets/css/app.css') ?>">
</head>
<body class="h-full bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-200 antialiased">
<div class="min-h-full flex flex-col">
<header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 backdrop-blur dark:border-slate-800 dark:bg-slate-900/85">
  <div class="mx-auto flex h-14 max-w-[1800px] items-center gap-4 px-4">
    <a href="chat.php" class="flex items-center gap-2.5">
      <img src="assets/img/emblem.png" class="h-9 w-9 object-contain" alt="" aria-hidden="true">
      <div class="leading-tight">
        <div class="text-[15px] font-bold tracking-tight"><?= MK_APP ?></div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400"><?= h(t('Masked AI chat')) ?></div>
      </div>
    </a>
    <nav class="ml-4 flex items-center gap-1 text-sm">
      <?php foreach (['chat' => ['chat.php', t('Chat')], 'index' => ['index.php', t('Mask')], 'guide' => ['guide.php', t('User Guide')]] as $k => [$href, $label]): ?>
        <a href="<?= $href ?>" class="rounded-md px-3 py-1.5 font-medium <?= $active === $k ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="ml-auto flex items-center gap-2">
      <?php foreach ($extra as $html) echo $html; ?>
      <?= mk_lang_switch() ?>
      <button type="button" id="themeToggle" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" title="<?= h(t('Toggle theme')) ?>">
        <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="hidden h-5 w-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <a href="settings.php" title="<?= h(t('Settings')) ?>" aria-label="<?= h(t('Settings')) ?>" <?= in_array($active, ['settings', 'admin-guide'], true) ? 'aria-current="page"' : '' ?>
        class="rounded-md p-2 <?= in_array($active, ['settings', 'admin-guide'], true) ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
      </a>
    </div>
  </div>
</header>
<main class="flex-1">
<?php
}

function mk_foot(array $scripts = []): void
{
    ?>
</main>
<footer class="border-t border-slate-200 py-3 text-center text-xs text-slate-400 dark:border-slate-800">
  <?= MK_APP ?> v<?= h(MK_VERSION) ?> · <?= h(t('Inputs are not stored on the server; the AI only receives masked text; the mapping table is kept only in your browser.')) ?>
</footer>
</div>
<div id="toasts" class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2"></div>
<script src="assets/js/common.js?v=<?= (int)@filemtime(MK_ROOT . '/assets/js/common.js') ?>"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= h($s) ?>?v=<?= (int)@filemtime(MK_ROOT . '/' . $s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
